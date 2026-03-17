<?php

namespace App\Services;

use App\Events\ExperimentProgressUpdated;
use App\Jobs\ExecuteExperimentRun;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\User;
use RuntimeException;
use Throwable;

class ExperimentService
{
    private const FINAL_STATUSES = ['success', 'invalid_format', 'failed'];

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly LLMProviderManager $providers,
        private readonly PromptCompiler $compiler,
        private readonly StructuredOutputValidator $validator,
    ) {
    }

    public function runInteractive(User $user, array $data): Experiment
    {
        $promptVersions = PromptVersion::with('promptTemplate.useCase')
            ->whereIn('id', $data['prompt_version_ids'])
            ->get()
            ->keyBy('id');

        $primaryVersion = $promptVersions->first();

        if (! $primaryVersion) {
            throw new RuntimeException('No prompt versions were selected.');
        }

        $experiment = Experiment::create([
            'team_id' => $primaryVersion->team_id,
            'use_case_id' => $primaryVersion->promptTemplate?->use_case_id,
            'created_by' => $user->id,
            'mode' => $data['mode'],
            'provider' => $this->providers->driverForModel($data['model_name']),
            'model_name' => $data['model_name'],
            'temperature' => $data['temperature'],
            'max_tokens' => $data['max_tokens'],
            'prompt_version_ids_json' => $data['prompt_version_ids'],
            'input_text' => $data['input_text'] ?? null,
            'variables_json' => $data['variables'] ?? [],
            'status' => 'running',
            'total_runs' => count($data['prompt_version_ids']),
            'started_at' => now(),
        ]);

        foreach ($data['prompt_version_ids'] as $promptVersionId) {
            $promptVersion = $promptVersions->get($promptVersionId);

            if (! $promptVersion) {
                continue;
            }

            $run = ExperimentRun::create([
                'team_id' => $experiment->team_id,
                'experiment_id' => $experiment->id,
                'prompt_version_id' => $promptVersion->id,
                'input_text' => (string) ($data['input_text'] ?? ''),
                'variables_json' => $data['variables'] ?? [],
                'status' => 'queued',
            ]);

            $this->executeRun($run);
        }

        return $this->loadExperiment($experiment->fresh());
    }

    public function queueBatch(User $user, array $data): Experiment
    {
        $promptVersion = PromptVersion::with('promptTemplate.useCase')
            ->findOrFail($data['prompt_version_ids'][0]);

        $testCases = TestCase::whereIn('id', $data['test_case_ids'])
            ->get()
            ->keyBy('id');

        $experiment = Experiment::create([
            'team_id' => $promptVersion->team_id,
            'use_case_id' => $promptVersion->promptTemplate?->use_case_id,
            'created_by' => $user->id,
            'mode' => 'batch',
            'provider' => $this->providers->driverForModel($data['model_name']),
            'model_name' => $data['model_name'],
            'temperature' => $data['temperature'],
            'max_tokens' => $data['max_tokens'],
            'prompt_version_ids_json' => $data['prompt_version_ids'],
            'variables_json' => $data['variables'] ?? [],
            'status' => 'running',
            'total_runs' => count($data['test_case_ids']),
            'started_at' => now(),
        ]);

        foreach ($data['test_case_ids'] as $testCaseId) {
            $testCase = $testCases->get($testCaseId);

            if (! $testCase) {
                continue;
            }

            $run = ExperimentRun::create([
                'team_id' => $experiment->team_id,
                'experiment_id' => $experiment->id,
                'prompt_version_id' => $promptVersion->id,
                'test_case_id' => $testCase->id,
                'input_text' => $testCase->input_text,
                'variables_json' => array_merge($testCase->variables_json ?? [], $data['variables'] ?? []),
                'status' => 'queued',
            ]);

            ExecuteExperimentRun::dispatch($run->id);
        }

        $this->refreshExperiment($experiment->fresh());

        return $this->loadExperiment($experiment->fresh());
    }

    public function executeRun(ExperimentRun|int $run): ExperimentRun
    {
        $run = $run instanceof ExperimentRun
            ? $run->loadMissing(['experiment', 'promptVersion.promptTemplate.useCase', 'testCase'])
            : ExperimentRun::with(['experiment', 'promptVersion.promptTemplate.useCase', 'testCase'])->findOrFail($run);

        $experiment = $run->experiment;
        $promptVersion = $run->promptVersion;

        $run->update([
            'status' => 'running',
            'error_message' => null,
        ]);

        try {
            $compiled = $this->compiler->compile($promptVersion, $run->input_text, $run->variables_json ?? []);

            if (! empty($compiled['missing'])) {
                throw new RuntimeException('Missing required variables: '.implode(', ', $compiled['missing']));
            }

            $response = $this->providers->runPrompt($compiled['final_prompt'], [
                'model' => $experiment->model_name,
                'temperature' => $experiment->temperature,
                'max_tokens' => $experiment->max_tokens,
                'task_type' => $promptVersion->promptTemplate?->task_type,
                'use_case_slug' => $promptVersion->promptTemplate?->useCase?->slug,
                'output_type' => $promptVersion->output_type,
                'output_schema' => $promptVersion->output_schema_json,
                'prompt_version_label' => $promptVersion->version_label,
                'system_prompt' => $compiled['system_prompt'],
                'user_prompt' => $compiled['user_prompt'],
            ]);

            $validation = $this->validator->validate($promptVersion, $response['output_text'] ?? '');
            $status = ($validation['format_valid'] ?? false) ? 'success' : 'invalid_format';

            $run->update([
                'compiled_prompt' => $compiled['final_prompt'],
                'output_text' => $response['output_text'] ?? null,
                'output_json' => $validation['output_json'],
                'latency_ms' => $response['latency_ms'] ?? null,
                'token_input' => $response['token_input'] ?? null,
                'token_output' => $response['token_output'] ?? null,
                'format_valid' => $validation['format_valid'],
                'status' => $status,
                'error_message' => $validation['error'],
                'provider_response_json' => $response['raw_response'] ?? null,
            ]);
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ]);
        }

        $this->refreshExperiment($experiment->fresh());

        return $run->fresh(['promptVersion.promptTemplate.useCase', 'testCase', 'evaluations']);
    }

    public function loadExperiment(Experiment $experiment): Experiment
    {
        return $experiment->load([
            'useCase',
            'creator',
            'runs.promptVersion.promptTemplate.useCase',
            'runs.testCase',
            'runs.evaluations.evaluator',
        ]);
    }

    public function refreshExperiment(Experiment $experiment): Experiment
    {
        $experiment->loadMissing('runs.evaluations');

        $runs = $experiment->runs;
        $totalRuns = $runs->count();
        $completedRuns = $runs->filter(
            fn (ExperimentRun $run) => in_array($run->status, self::FINAL_STATUSES, true)
        )->count();
        $failedRuns = $runs->where('status', 'failed')->count();

        $status = match (true) {
            $totalRuns === 0 => 'queued',
            $completedRuns < $totalRuns => 'running',
            $failedRuns === $totalRuns => 'failed',
            default => 'completed',
        };

        $experiment->update([
            'status' => $status,
            'completed_runs' => $completedRuns,
            'failed_runs' => $failedRuns,
            'completed_at' => $completedRuns === $totalRuns ? now() : null,
            'summary_json' => $this->analytics->experimentSummary($experiment),
        ]);

        event(new ExperimentProgressUpdated($experiment->fresh()));

        return $experiment->fresh();
    }
}

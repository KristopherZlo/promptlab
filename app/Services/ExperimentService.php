<?php

namespace App\Services;

use App\Events\ExperimentProgressUpdated;
use App\Exceptions\TerminalOperationException;
use App\Jobs\ExecuteExperimentRun;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        return $this->createAndDispatchExperiment(
            $user,
            $data,
            $primaryVersion->team_id,
            $primaryVersion->promptTemplate?->use_case_id,
            function (Experiment $experiment) use ($data, $promptVersions): array {
                return collect($data['prompt_version_ids'])
                    ->map(fn (int $promptVersionId) => $promptVersions->get($promptVersionId))
                    ->filter()
                    ->map(fn (PromptVersion $promptVersion) => ExperimentRun::create([
                        'team_id' => $experiment->team_id,
                        'experiment_id' => $experiment->id,
                        'prompt_version_id' => $promptVersion->id,
                        'input_text' => (string) ($data['input_text'] ?? ''),
                        'variables_json' => $data['variables'] ?? [],
                        'status' => 'queued',
                    ])->id)
                    ->values()
                    ->all();
            },
        );
    }

    public function queueBatch(User $user, array $data): Experiment
    {
        $promptVersion = PromptVersion::with('promptTemplate.useCase')
            ->findOrFail($data['prompt_version_ids'][0]);

        $testCases = TestCase::whereIn('id', $data['test_case_ids'])
            ->get()
            ->keyBy('id');

        return $this->createAndDispatchExperiment(
            $user,
            $data,
            $promptVersion->team_id,
            $promptVersion->promptTemplate?->use_case_id,
            function (Experiment $experiment) use ($data, $promptVersion, $testCases): array {
                return collect($data['test_case_ids'])
                    ->map(fn (int $testCaseId) => $testCases->get($testCaseId))
                    ->filter()
                    ->map(fn (TestCase $testCase) => ExperimentRun::create([
                        'team_id' => $experiment->team_id,
                        'experiment_id' => $experiment->id,
                        'prompt_version_id' => $promptVersion->id,
                        'test_case_id' => $testCase->id,
                        'input_text' => $testCase->input_text,
                        'variables_json' => array_merge($testCase->variables_json ?? [], $data['variables'] ?? []),
                        'status' => 'queued',
                    ])->id)
                    ->values()
                    ->all();
            },
        );
    }

    public function executeRun(ExperimentRun|int $run): ExperimentRun
    {
        $runId = $run instanceof ExperimentRun ? $run->id : $run;

        if (! $this->claimRun($runId)) {
            return $this->loadRun($runId, ['evaluations']);
        }

        $run = $this->loadRun($runId);
        $experiment = $run->experiment;
        $promptVersion = $run->promptVersion;

        Experiment::withoutGlobalScopes()
            ->whereKey($experiment->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'started_at' => $experiment->started_at ?? now(),
                'completed_at' => null,
            ]);

        $compiled = $this->compiler->compile($promptVersion, $run->input_text, $run->variables_json ?? []);

        if (! empty($compiled['missing'])) {
            throw new TerminalOperationException('Missing required variables: '.implode(', ', $compiled['missing']));
        }

        $response = $this->providers->runPrompt($compiled['final_prompt'], [
            'team_id' => $experiment->team_id,
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

        $this->refreshExperiment($experiment->fresh());

        return $this->loadRun($runId, ['evaluations']);
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
            $completedRuns === 0 && $failedRuns === 0 && $experiment->started_at === null => 'queued',
            $completedRuns < $totalRuns => 'running',
            $failedRuns === $totalRuns => 'failed',
            default => 'completed',
        };

        $experiment->update([
            'status' => $status,
            'completed_runs' => $completedRuns,
            'failed_runs' => $failedRuns,
            'completed_at' => $completedRuns === $totalRuns ? now() : null,
            'summary_json' => $completedRuns > 0 ? $this->analytics->experimentSummary($experiment) : ($experiment->summary_json ?? []),
        ]);

        event(new ExperimentProgressUpdated($experiment->fresh()));

        return $experiment->fresh();
    }

    public function markRunForRetry(int $runId, \Throwable $throwable): ExperimentRun
    {
        $run = ExperimentRun::withoutGlobalScopes()->findOrFail($runId);

        if (in_array($run->status, self::FINAL_STATUSES, true)) {
            return $this->loadRun($runId, ['evaluations']);
        }

        $run->update([
            'status' => 'queued',
            'error_message' => $throwable->getMessage(),
        ]);

        $this->refreshExperiment($run->experiment()->withoutGlobalScopes()->firstOrFail());

        return $this->loadRun($runId, ['evaluations']);
    }

    public function markRunFailed(int $runId, \Throwable|string $error): ExperimentRun
    {
        $run = ExperimentRun::withoutGlobalScopes()->findOrFail($runId);

        if (in_array($run->status, ['success', 'invalid_format'], true)) {
            return $this->loadRun($runId, ['evaluations']);
        }

        $run->update([
            'status' => 'failed',
            'error_message' => is_string($error) ? $error : $error->getMessage(),
        ]);

        $this->refreshExperiment($run->experiment()->withoutGlobalScopes()->firstOrFail());

        return $this->loadRun($runId, ['evaluations']);
    }

    private function createAndDispatchExperiment(
        User $user,
        array $data,
        int $teamId,
        ?int $useCaseId,
        callable $createRuns,
    ): Experiment {
        [$experiment, $runIds] = DB::transaction(function () use ($user, $data, $teamId, $useCaseId, $createRuns) {
            $experiment = Experiment::create([
                'team_id' => $teamId,
                'use_case_id' => $useCaseId,
                'created_by' => $user->id,
                'mode' => $data['mode'],
                'provider' => $this->providers->driverForModel($data['model_name'], $teamId),
                'model_name' => $data['model_name'],
                'temperature' => $data['temperature'],
                'max_tokens' => $data['max_tokens'],
                'prompt_version_ids_json' => $data['prompt_version_ids'],
                'input_text' => $data['input_text'] ?? null,
                'variables_json' => $data['variables'] ?? [],
                'summary_json' => [],
                'status' => 'queued',
                'total_runs' => $data['mode'] === 'batch'
                    ? count($data['test_case_ids'] ?? [])
                    : count($data['prompt_version_ids'] ?? []),
                'completed_runs' => 0,
                'failed_runs' => 0,
                'started_at' => null,
                'completed_at' => null,
            ]);

            return [$experiment, $createRuns($experiment)];
        });

        foreach ($runIds as $runId) {
            ExecuteExperimentRun::dispatch($runId);
        }

        return $this->loadExperiment($experiment->fresh());
    }

    private function claimRun(int $runId): bool
    {
        $run = ExperimentRun::withoutGlobalScopes()->findOrFail($runId);

        if ($run->status !== 'queued') {
            return false;
        }

        return ExperimentRun::withoutGlobalScopes()
            ->whereKey($runId)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'error_message' => null,
                'compiled_prompt' => null,
                'output_text' => null,
                'output_json' => null,
                'provider_response_json' => null,
                'format_valid' => null,
                'latency_ms' => null,
                'token_input' => null,
                'token_output' => null,
            ]) === 1;
    }

    private function loadRun(int $runId, array $with = []): ExperimentRun
    {
        return ExperimentRun::withoutGlobalScopes()
            ->with(array_merge([
                'experiment',
                'promptVersion.promptTemplate.useCase',
                'testCase',
            ], $with))
            ->findOrFail($runId);
    }
}

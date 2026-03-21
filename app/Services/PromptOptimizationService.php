<?php

namespace App\Services;

use App\Http\Resources\PromptOptimizationRunResource;
use App\Models\PromptOptimizationRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromptOptimizationService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly AllowedModelService $allowedModels,
        private readonly GepaPromptOptimizer $optimizer,
        private readonly LLMProviderManager $providers,
    ) {
    }

    public function contextForTemplate(PromptTemplate $promptTemplate): array
    {
        $promptTemplate->loadMissing([
            'useCase.testCases',
            'optimizationRuns.creator',
            'optimizationRuns.sourceVersion',
            'optimizationRuns.derivedVersion',
        ]);

        $eligibleCases = $this->eligibleCases($promptTemplate);
        $split = $this->splitCases($eligibleCases);

        return [
            'eligible_test_case_count' => $eligibleCases->count(),
            'train_case_count' => $split['train']->count(),
            'validation_case_count' => $split['validation']->count(),
            'eligible_test_cases' => $eligibleCases
                ->map(fn (TestCase $testCase) => [
                    'id' => $testCase->id,
                    'title' => $testCase->title,
                    'status' => $testCase->status,
                    'has_expected_output' => filled($testCase->expected_output),
                    'has_expected_json' => ! empty($testCase->expected_json ?? []),
                ])
                ->values()
                ->all(),
            'default_source_prompt_version_id' => $promptTemplate->versions()->latest('id')->value('id'),
            'runs' => PromptOptimizationRunResource::collection($promptTemplate->optimizationRuns)->resolve(),
        ];
    }

    public function startRun(User $user, PromptTemplate $promptTemplate, array $data): PromptOptimizationRun
    {
        $promptTemplate->loadMissing(['versions', 'useCase.testCases']);

        $sourceVersion = $promptTemplate->versions
            ->firstWhere('id', $data['source_prompt_version_id']);

        if (! $sourceVersion) {
            throw ValidationException::withMessages([
                'source_prompt_version_id' => 'Select a saved prompt version before starting optimization.',
            ]);
        }

        if (! $this->allowedModels->isAllowed($data['model_name'], $promptTemplate->team_id)) {
            throw ValidationException::withMessages([
                'model_name' => 'Select a configured model that is available in this workspace.',
            ]);
        }

        $eligibleCases = $this->eligibleCases($promptTemplate);

        if ($eligibleCases->isEmpty()) {
            throw ValidationException::withMessages([
                'source_prompt_version_id' => 'Add at least one active test case with expected output or expected JSON before running GEPA.',
            ]);
        }

        $split = $this->splitCases($eligibleCases);
        $budget = min(
            max((int) $data['budget_metric_calls'], 6),
            max((int) config('gepa.max_budget_metric_calls', 60), 6)
        );
        $config = $this->runConfig($budget);

        $run = DB::transaction(function () use ($budget, $config, $data, $eligibleCases, $promptTemplate, $sourceVersion, $split, $user) {
            $run = PromptOptimizationRun::create([
                'team_id' => $promptTemplate->team_id,
                'prompt_template_id' => $promptTemplate->id,
                'use_case_id' => $promptTemplate->use_case_id,
                'source_prompt_version_id' => $sourceVersion->id,
                'created_by' => $user->id,
                'requested_model_name' => $data['model_name'],
                'status' => 'queued',
                'budget_metric_calls' => $budget,
                'train_case_ids_json' => $split['train']->pluck('id')->values()->all(),
                'validation_case_ids_json' => $split['validation']->pluck('id')->values()->all(),
                'config_json' => $config,
                'seed_candidate_json' => $this->seedCandidate($sourceVersion),
            ]);

            $this->activity->record('prompt_optimization.started', $run, [
                'prompt_template_id' => $promptTemplate->id,
                'prompt_template_name' => $promptTemplate->name,
                'source_version_label' => $sourceVersion->version_label,
                'model_name' => $run->requested_model_name,
                'budget_metric_calls' => $budget,
                'eligible_cases' => $eligibleCases->count(),
            ], $user, $promptTemplate->team_id);

            return $run;
        });

        \App\Jobs\ExecutePromptOptimization::dispatch($run->id);

        return $this->loadRun($run->fresh());
    }

    public function executeRun(PromptOptimizationRun|int $run): PromptOptimizationRun
    {
        $runId = $run instanceof PromptOptimizationRun ? $run->id : $run;
        $run = $run instanceof PromptOptimizationRun ? $this->loadRun($run) : $this->loadRun(PromptOptimizationRun::query()->findOrFail($runId));

        if (! $this->claimRun($runId)) {
            return $this->loadRun(PromptOptimizationRun::query()->findOrFail($runId));
        }

        $run = $this->loadRun(PromptOptimizationRun::query()->findOrFail($runId));

        $payload = $this->buildPayload($run);
        $result = $this->optimizer->optimize($run, $payload);

        $bestCandidate = $result['best_candidate'] ?? null;

        if (! is_array($bestCandidate)) {
            throw new \RuntimeException('GEPA did not return a prompt candidate.');
        }

        $derivedVersion = DB::transaction(function () use ($bestCandidate, $result, $run) {
            $freshRun = $this->loadRun($run->fresh());
            $derivedVersion = $this->createDerivedVersionIfChanged(
                $freshRun,
                $bestCandidate,
                isset($result['best_score']) ? (float) $result['best_score'] : null,
            );

            $freshRun->update([
                'status' => 'completed',
                'best_score' => $result['best_score'] ?? null,
                'total_metric_calls' => $result['total_metric_calls'] ?? data_get($result, 'result.total_metric_calls'),
                'candidate_count' => $result['candidate_count'] ?? count(data_get($result, 'result.candidates', [])),
                'best_candidate_json' => $bestCandidate,
                'result_json' => $result['result'] ?? null,
                'derived_prompt_version_id' => $derivedVersion?->id,
                'completed_at' => now(),
            ]);

            return $derivedVersion;
        });

        $this->activity->record('prompt_optimization.completed', $run->fresh(), [
            'prompt_template_name' => $run->promptTemplate?->name,
            'source_version_label' => $run->sourceVersion?->version_label,
            'derived_version_label' => $derivedVersion?->version_label,
            'best_score' => $run->fresh()->best_score,
            'metric_calls' => $run->fresh()->total_metric_calls,
        ], $run->creator, $run->team_id);

        return $this->loadRun($run->fresh());
    }

    public function markRunFailed(int $runId, \Throwable|string $error): PromptOptimizationRun
    {
        $run = PromptOptimizationRun::withoutGlobalScopes()->findOrFail($runId);

        if ($run->status === 'completed') {
            return $this->loadRun($run);
        }

        $message = Str::limit(is_string($error) ? $error : $error->getMessage(), 4000, '...');

        $run->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        $this->activity->record('prompt_optimization.failed', $run->fresh(), [
            'prompt_template_name' => $run->promptTemplate?->name,
            'source_version_label' => $run->sourceVersion?->version_label,
            'error' => $message,
        ], $run->creator, $run->team_id);

        return $this->loadRun($run->fresh());
    }

    public function eligibleCases(PromptTemplate $promptTemplate): Collection
    {
        $promptTemplate->loadMissing('useCase.testCases');

        return $promptTemplate->useCase?->testCases
            ? $promptTemplate->useCase->testCases
                ->filter(fn (TestCase $testCase) => $testCase->status === 'active')
                ->filter(fn (TestCase $testCase) => filled($testCase->expected_output) || ! empty($testCase->expected_json ?? []))
                ->sortBy('id')
                ->values()
            : new Collection();
    }

    private function splitCases(Collection $cases): array
    {
        $count = $cases->count();

        if ($count === 0) {
            return [
                'train' => new Collection(),
                'validation' => new Collection(),
            ];
        }

        if ($count === 1) {
            return [
                'train' => $cases,
                'validation' => $cases,
            ];
        }

        if ($count === 2) {
            return [
                'train' => new Collection([$cases->first()]),
                'validation' => new Collection([$cases->last()]),
            ];
        }

        $validationCount = max(1, (int) floor($count * 0.3));
        $trainCount = max(1, $count - $validationCount);

        return [
            'train' => $cases->take($trainCount)->values(),
            'validation' => $cases->slice($trainCount)->values(),
        ];
    }

    private function buildPayload(PromptOptimizationRun $run): array
    {
        $run->loadMissing('promptTemplate.useCase', 'sourceVersion');

        $trainCases = TestCase::query()
            ->where('team_id', $run->team_id)
            ->whereIn('id', $run->train_case_ids_json ?? [])
            ->orderBy('id')
            ->get();
        $validationCases = TestCase::query()
            ->where('team_id', $run->team_id)
            ->whereIn('id', $run->validation_case_ids_json ?? [])
            ->orderBy('id')
            ->get();

        $sourceVersion = $run->sourceVersion;
        $promptTemplate = $run->promptTemplate;
        $useCase = $promptTemplate?->useCase;

        return [
            'run_id' => $run->id,
            'project_root' => base_path(),
            'php_binary' => PHP_BINARY,
            'artisan_path' => base_path('artisan'),
            'seed_candidate' => $run->seed_candidate_json ?? $this->seedCandidate($sourceVersion),
            'dataset' => $this->serializeCases($trainCases),
            'valset' => $this->serializeCases($validationCases),
            'budget_metric_calls' => $run->budget_metric_calls,
            'objective' => trim(implode(' ', array_filter([
                'Optimize this prompt pair so it scores higher on the workspace automatic checks.',
                $promptTemplate?->name ? "Prompt family: {$promptTemplate->name}." : null,
                $useCase?->name ? "Task: {$useCase->name}." : null,
                'Preserve the task intent, output contract, and required variables.',
            ]))),
            'background' => trim(implode("\n\n", array_filter([
                $useCase?->description ? "Use case description:\n{$useCase->description}" : null,
                $useCase?->business_goal ? "Business goal:\n{$useCase->business_goal}" : null,
                $promptTemplate?->description ? "Prompt template description:\n{$promptTemplate->description}" : null,
                $sourceVersion?->change_summary ? "Current version summary:\n{$sourceVersion->change_summary}" : null,
                ! empty($sourceVersion?->variables_schema ?? []) ? "Variables schema:\n".$this->jsonPreview($sourceVersion->variables_schema) : null,
                ($sourceVersion?->output_type === 'json' && ! empty($sourceVersion?->output_schema_json ?? []))
                    ? "Output schema:\n".$this->jsonPreview($sourceVersion->output_schema_json)
                    : null,
            ]))),
        ];
    }

    private function serializeCases(Collection $cases): array
    {
        return $cases->map(fn (TestCase $testCase) => [
            'id' => $testCase->id,
            'title' => $testCase->title,
            'input_text' => $testCase->input_text,
            'expected_output' => $testCase->expected_output,
            'expected_json' => $testCase->expected_json ?? [],
            'variables_json' => $testCase->variables_json ?? [],
        ])->values()->all();
    }

    private function seedCandidate(?PromptVersion $sourceVersion): array
    {
        return [
            'system_prompt' => (string) ($sourceVersion?->system_prompt ?? ''),
            'user_prompt_template' => (string) ($sourceVersion?->user_prompt_template ?? ''),
        ];
    }

    private function runConfig(int $budget): array
    {
        return [
            'budget_metric_calls' => $budget,
            'reflection' => [
                'temperature' => config('gepa.reflection_temperature'),
                'max_tokens' => config('gepa.reflection_max_tokens'),
            ],
            'evaluation' => [
                'temperature' => config('gepa.evaluation_temperature'),
                'max_tokens' => config('gepa.evaluation_max_tokens'),
            ],
        ];
    }

    private function createDerivedVersionIfChanged(
        PromptOptimizationRun $run,
        array $bestCandidate,
        ?float $bestScore = null,
    ): ?PromptVersion
    {
        $sourceVersion = $run->sourceVersion;
        $promptTemplate = $run->promptTemplate;

        if (! $sourceVersion || ! $promptTemplate) {
            return null;
        }

        $nextSystemPrompt = (string) ($bestCandidate['system_prompt'] ?? '');
        $nextUserPrompt = (string) ($bestCandidate['user_prompt_template'] ?? '');
        $sourceSystemPrompt = (string) ($sourceVersion->system_prompt ?? '');
        $sourceUserPrompt = (string) ($sourceVersion->user_prompt_template ?? '');

        if (
            $this->normalizeCandidate($nextSystemPrompt) === $this->normalizeCandidate($sourceSystemPrompt)
            && $this->normalizeCandidate($nextUserPrompt) === $this->normalizeCandidate($sourceUserPrompt)
        ) {
            return null;
        }

        $versionLabel = $this->nextVersionLabel($promptTemplate);

        return PromptVersion::create([
            'team_id' => $run->team_id,
            'prompt_template_id' => $promptTemplate->id,
            'version_label' => $versionLabel,
            'change_summary' => Str::limit("GEPA optimization draft from {$sourceVersion->version_label}", 255, ''),
            'system_prompt' => $nextSystemPrompt !== '' ? $nextSystemPrompt : null,
            'user_prompt_template' => $nextUserPrompt,
            'variables_schema' => $sourceVersion->variables_schema ?? [],
            'output_type' => $sourceVersion->output_type,
            'output_schema_json' => $sourceVersion->output_schema_json ?? [],
            'notes' => trim(implode("\n\n", array_filter([
                $sourceVersion->notes,
                'Generated by GEPA optimization.',
                "Source version: {$sourceVersion->version_label}",
                "Requested model: {$run->requested_model_name}",
                $bestScore !== null ? 'Best validation score: '.number_format($bestScore, 4) : null,
            ]))),
            'preferred_model' => $run->requested_model_name,
            'created_by' => $run->created_by,
            'updated_by' => $run->created_by,
        ]);
    }

    private function nextVersionLabel(PromptTemplate $promptTemplate): string
    {
        $versionNumber = max(1, $promptTemplate->versions()->count() + 1);

        do {
            $candidate = 'v'.$versionNumber;
            $exists = $promptTemplate->versions()->where('version_label', $candidate)->exists();
            $versionNumber++;
        } while ($exists);

        return $candidate;
    }

    private function normalizeCandidate(string $value): string
    {
        return trim(str_replace("\r\n", "\n", $value));
    }

    private function jsonPreview(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function loadRun(PromptOptimizationRun $run): PromptOptimizationRun
    {
        return $run->load([
            'creator',
            'promptTemplate.useCase',
            'sourceVersion',
            'derivedVersion',
        ]);
    }

    private function claimRun(int $runId): bool
    {
        $run = PromptOptimizationRun::withoutGlobalScopes()->findOrFail($runId);

        if ($run->status !== 'queued') {
            return false;
        }

        return PromptOptimizationRun::withoutGlobalScopes()
            ->whereKey($runId)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'started_at' => $run->started_at ?? now(),
                'completed_at' => null,
                'error_message' => null,
            ]) === 1;
    }

}

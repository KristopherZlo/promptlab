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

    private function loadRun(PromptOptimizationRun $run): PromptOptimizationRun
    {
        return $run->load([
            'creator',
            'promptTemplate.useCase',
            'sourceVersion',
            'derivedVersion',
        ]);
    }

}

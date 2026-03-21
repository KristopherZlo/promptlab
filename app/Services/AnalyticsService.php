<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\LibraryEntry;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\UseCase;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function __construct(
        private readonly AutomaticEvaluationService $automaticEvaluations,
    ) {
    }

    public function overview(): array
    {
        return [
            'counts' => [
                'use_cases' => UseCase::count(),
                'prompt_templates' => \App\Models\PromptTemplate::count(),
                'runs' => ExperimentRun::count(),
                'library_entries' => LibraryEntry::count(),
            ],
            'recent_experiments' => Experiment::with(['useCase', 'creator'])
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (Experiment $experiment) => [
                    'id' => $experiment->id,
                    'use_case_id' => $experiment->use_case_id,
                    'mode' => $experiment->mode,
                    'status' => $experiment->status,
                    'use_case' => $experiment->useCase?->name,
                    'model_name' => $experiment->model_name,
                    'completed_runs' => $experiment->completed_runs,
                    'total_runs' => $experiment->total_runs,
                    'created_by' => $experiment->creator?->name,
                    'created_at' => optional($experiment->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'most_used_prompts' => PromptVersion::query()
                ->withSummaryMetrics()
                ->with(['promptTemplate.useCase'])
                ->orderByDesc('run_count')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->map(fn (PromptVersion $version) => [
                    'id' => $version->id,
                    'prompt_template_id' => $version->prompt_template_id,
                    'use_case_id' => $version->promptTemplate?->use_case_id,
                    'name' => $version->promptTemplate?->name,
                    'version_label' => $version->version_label,
                    'use_case' => $version->promptTemplate?->useCase?->name,
                    'runs' => (int) ($version->run_count ?? 0),
                ])
                ->values()
                ->all(),
            'top_performing_prompts' => PromptVersion::query()
                ->withSummaryMetrics()
                ->with(['promptTemplate.useCase'])
                ->whereHas('experimentRuns.evaluations')
                ->orderByDesc('average_score')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->filter(fn (PromptVersion $version) => $version->average_score !== null)
                ->map(fn (PromptVersion $version) => $this->promptSummary($version))
                ->values()
                ->all(),
            'failed_format_outputs' => ExperimentRun::with(['promptVersion.promptTemplate.useCase', 'experiment'])
                ->where('status', 'invalid_format')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (ExperimentRun $run) => [
                    'id' => $run->id,
                    'experiment_id' => $run->experiment_id,
                    'prompt_version_id' => $run->prompt_version_id,
                    'prompt_template_id' => $run->promptVersion?->prompt_template_id,
                    'use_case_id' => $run->promptVersion?->promptTemplate?->use_case_id,
                    'prompt' => $run->promptVersion?->promptTemplate?->name,
                    'version_label' => $run->promptVersion?->version_label,
                    'use_case' => $run->promptVersion?->promptTemplate?->useCase?->name,
                    'model_name' => $run->experiment?->model_name,
                    'error' => $run->error_message,
                    'created_at' => optional($run->created_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'problem_cases' => TestCase::with('useCase')
                ->withCount([
                    'experimentRuns as failed_count' => fn ($query) => $query->whereIn('status', ['failed', 'invalid_format']),
                ])
                ->orderByDesc('failed_count')
                ->take(5)
                ->get()
                ->map(fn (TestCase $testCase) => [
                    'id' => $testCase->id,
                    'use_case_id' => $testCase->use_case_id,
                    'title' => $testCase->title,
                    'use_case' => $testCase->useCase?->name,
                    'failed_count' => $testCase->failed_count,
                ])
                ->values()
                ->all(),
            'top_models' => Experiment::query()
                ->join('experiment_runs', 'experiment_runs.experiment_id', '=', 'experiments.id')
                ->join('evaluations', 'evaluations.experiment_run_id', '=', 'experiment_runs.id')
                ->select('experiments.model_name')
                ->selectRaw('round(avg('.PromptVersion::evaluationAverageScoreSql('evaluations').'), 2) as average_score')
                ->selectRaw('count(distinct experiment_runs.id) as runs')
                ->groupBy('experiments.model_name')
                ->orderByDesc('average_score')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'model_name' => $row->model_name,
                    'average_score' => $row->average_score !== null ? (float) $row->average_score : null,
                    'runs' => (int) $row->runs,
                ])
                ->filter(fn (array $row) => $row['average_score'] !== null)
                ->values()
                ->all(),
        ];
    }

    public function bestPromptForUseCase(UseCase $useCase): ?array
    {
        $version = PromptVersion::query()
            ->withSummaryMetrics()
            ->with(['promptTemplate.useCase'])
            ->whereHas('promptTemplate', fn ($query) => $query->where('use_case_id', $useCase->id))
            ->whereHas('experimentRuns.evaluations')
            ->orderByDesc('average_score')
            ->orderByDesc('id')
            ->first();

        return $version ? $this->promptSummary($version) : null;
    }

    public function bestPromptsForUseCases(iterable $useCaseIds): Collection
    {
        $ids = collect($useCaseIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return PromptVersion::query()
            ->select('prompt_versions.*')
            ->join('prompt_templates', 'prompt_templates.id', '=', 'prompt_versions.prompt_template_id')
            ->withSummaryMetrics()
            ->with(['promptTemplate.useCase'])
            ->whereIn('prompt_templates.use_case_id', $ids)
            ->whereHas('experimentRuns.evaluations')
            ->orderBy('prompt_templates.use_case_id')
            ->orderByDesc('average_score')
            ->orderByDesc('prompt_versions.id')
            ->get()
            ->groupBy(fn (PromptVersion $version) => $version->promptTemplate?->use_case_id)
            ->map(fn (Collection $group) => $this->promptSummary($group->first()));
    }

    public function useCaseDetail(UseCase $useCase): array
    {
        $runsQuery = ExperimentRun::query()
            ->join('prompt_versions', 'prompt_versions.id', '=', 'experiment_runs.prompt_version_id')
            ->join('prompt_templates', 'prompt_templates.id', '=', 'prompt_versions.prompt_template_id')
            ->where('prompt_templates.use_case_id', $useCase->id);

        $totalRuns = (clone $runsQuery)->count();
        $formatPassRate = $totalRuns > 0
            ? round((((clone $runsQuery)->where('experiment_runs.format_valid', true)->count()) / $totalRuns) * 100, 1)
            : null;
        $averageScore = (clone $runsQuery)
            ->join('evaluations', 'evaluations.experiment_run_id', '=', 'experiment_runs.id')
            ->selectRaw('round(avg('.PromptVersion::evaluationAverageScoreSql('evaluations').'), 2) as average_score')
            ->value('average_score');

        return [
            'best_prompt' => $this->bestPromptForUseCase($useCase),
            'runs_count' => $totalRuns,
            'average_score' => $averageScore !== null ? (float) $averageScore : null,
            'format_pass_rate' => $formatPassRate,
            'prompt_templates_count' => $useCase->promptTemplates()->count(),
            'test_cases_count' => $useCase->testCases()->count(),
        ];
    }

    public function experimentSummary(Experiment $experiment): array
    {
        $experiment->loadMissing('runs.evaluations');

        $runs = $experiment->runs;
        $evaluations = $runs->flatMap->evaluations;
        $totalRuns = $runs->count();
        $automaticResults = $runs
            ->mapWithKeys(fn (ExperimentRun $run) => [$run->id => $this->automaticEvaluations->evaluateRun($run)]);
        $automaticEvaluatedRuns = $automaticResults->filter(fn (array $result) => $result['configured']);
        $formatPassRate = $totalRuns > 0
            ? round(($runs->where('format_valid', true)->count() / $totalRuns) * 100, 1)
            : null;
        $automaticPassRate = $automaticEvaluatedRuns->isNotEmpty()
            ? round(($automaticEvaluatedRuns->where('passed', true)->count() / $automaticEvaluatedRuns->count()) * 100, 1)
            : null;

        return [
            'average_manual_score' => $this->averageScoreFromEvaluations($evaluations),
            'format_pass_rate' => $formatPassRate,
            'automatic_pass_rate' => $automaticPassRate,
            'average_latency_ms' => $runs->whereNotNull('latency_ms')->avg('latency_ms')
                ? round($runs->whereNotNull('latency_ms')->avg('latency_ms'))
                : null,
            'evaluated_runs' => $evaluations->count(),
            'automatic_evaluated_runs' => $automaticEvaluatedRuns->count(),
            'most_common_errors' => $runs
                ->filter(fn (ExperimentRun $run) => in_array($run->status, ['failed', 'invalid_format'], true))
                ->map(fn (ExperimentRun $run) => $run->error_message ?: 'Invalid format')
                ->countBy()
                ->sortDesc()
                ->take(3)
                ->all(),
            'failed_test_case_ids' => $runs
                ->filter(fn (ExperimentRun $run) => in_array($run->status, ['failed', 'invalid_format'], true))
                ->pluck('test_case_id')
                ->filter()
                ->values()
                ->all(),
            'failed_automatic_test_case_ids' => $runs
                ->filter(function (ExperimentRun $run) use ($automaticResults): bool {
                    $result = $automaticResults->get($run->id);

                    return (bool) ($result['configured'] ?? false) && ($result['passed'] === false);
                })
                ->pluck('test_case_id')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function promptSummary(PromptVersion $version): array
    {
        return [
            'id' => $version->id,
            'prompt_template_id' => $version->prompt_template_id,
            'use_case_id' => $version->promptTemplate?->use_case_id,
            'name' => $version->promptTemplate?->name,
            'version_label' => $version->version_label,
            'use_case' => $version->promptTemplate?->useCase?->name,
            'average_score' => $version->average_score !== null ? round((float) $version->average_score, 2) : null,
            'format_pass_rate' => $version->format_pass_rate !== null ? (float) $version->format_pass_rate : null,
            'evaluated_runs' => (int) ($version->evaluation_count ?? 0),
            'total_runs' => (int) ($version->run_count ?? 0),
            'recommended_model' => $version->preferred_model ?: $version->promptTemplate?->preferred_model,
        ];
    }

    private function averageScoreFromEvaluations(Collection $evaluations): ?float
    {
        $scores = $evaluations
            ->map(fn (Evaluation $evaluation) => $evaluation->averageScore())
            ->filter(fn ($score) => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return round($scores->avg(), 2);
    }
}

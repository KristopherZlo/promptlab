<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\UseCase;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function overview(): array
    {
        return [
            'counts' => [
                'use_cases' => UseCase::count(),
                'prompt_templates' => PromptTemplate::count(),
                'runs' => ExperimentRun::count(),
                'library_entries' => LibraryEntry::count(),
            ],
            'recent_experiments' => Experiment::with(['useCase', 'creator'])
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (Experiment $experiment) => [
                    'id' => $experiment->id,
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
            'most_used_prompts' => PromptVersion::with(['promptTemplate.useCase'])
                ->withCount('experimentRuns')
                ->orderByDesc('experiment_runs_count')
                ->take(5)
                ->get()
                ->map(fn (PromptVersion $version) => [
                    'id' => $version->id,
                    'name' => $version->promptTemplate?->name,
                    'version_label' => $version->version_label,
                    'use_case' => $version->promptTemplate?->useCase?->name,
                    'runs' => $version->experiment_runs_count,
                ])
                ->values()
                ->all(),
            'top_performing_prompts' => $this->promptLeaderboard(
                PromptVersion::with(['promptTemplate.useCase', 'experimentRuns.evaluations'])->get()
            )->take(5)->values()->all(),
            'failed_format_outputs' => ExperimentRun::with(['promptVersion.promptTemplate.useCase', 'experiment'])
                ->where('status', 'invalid_format')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (ExperimentRun $run) => [
                    'id' => $run->id,
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
                    'title' => $testCase->title,
                    'use_case' => $testCase->useCase?->name,
                    'failed_count' => $testCase->failed_count,
                ])
                ->values()
                ->all(),
            'top_models' => Experiment::with('runs.evaluations')
                ->get()
                ->groupBy('model_name')
                ->map(function (Collection $experiments, string $model) {
                    $evaluations = $experiments
                        ->flatMap->runs
                        ->flatMap->evaluations;

                    return [
                        'model_name' => $model,
                        'average_score' => $this->averageScoreFromEvaluations($evaluations),
                        'runs' => $experiments->sum('total_runs'),
                    ];
                })
                ->filter(fn (array $row) => $row['average_score'] !== null)
                ->sortByDesc('average_score')
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    public function bestPromptForUseCase(UseCase $useCase): ?array
    {
        $promptVersions = PromptVersion::query()
            ->whereHas('promptTemplate', fn ($query) => $query->where('use_case_id', $useCase->id))
            ->with(['promptTemplate.useCase', 'experimentRuns.evaluations'])
            ->get();

        return $this->promptLeaderboard($promptVersions)->first();
    }

    public function useCaseDetail(UseCase $useCase): array
    {
        $runs = ExperimentRun::query()
            ->whereHas('promptVersion.promptTemplate', fn ($query) => $query->where('use_case_id', $useCase->id))
            ->with('evaluations')
            ->get();

        $evaluations = $runs->flatMap->evaluations;
        $totalRuns = $runs->count();
        $formatPassRate = $totalRuns > 0
            ? round(($runs->where('format_valid', true)->count() / $totalRuns) * 100, 1)
            : null;

        return [
            'best_prompt' => $this->bestPromptForUseCase($useCase),
            'runs_count' => $totalRuns,
            'average_score' => $this->averageScoreFromEvaluations($evaluations),
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
        $formatPassRate = $totalRuns > 0
            ? round(($runs->where('format_valid', true)->count() / $totalRuns) * 100, 1)
            : null;

        return [
            'average_manual_score' => $this->averageScoreFromEvaluations($evaluations),
            'format_pass_rate' => $formatPassRate,
            'average_latency_ms' => $runs->whereNotNull('latency_ms')->avg('latency_ms')
                ? round($runs->whereNotNull('latency_ms')->avg('latency_ms'))
                : null,
            'evaluated_runs' => $evaluations->count(),
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
        ];
    }

    private function promptLeaderboard(Collection $promptVersions): Collection
    {
        return $promptVersions
            ->map(function (PromptVersion $version) {
                $runs = $version->experimentRuns;
                $evaluations = $runs->flatMap->evaluations;
                $averageScore = $this->averageScoreFromEvaluations($evaluations);
                $formatPassRate = $runs->count() > 0
                    ? round(($runs->where('format_valid', true)->count() / $runs->count()) * 100, 1)
                    : null;

                return [
                    'id' => $version->id,
                    'name' => $version->promptTemplate?->name,
                    'version_label' => $version->version_label,
                    'use_case' => $version->promptTemplate?->useCase?->name,
                    'average_score' => $averageScore,
                    'format_pass_rate' => $formatPassRate,
                    'evaluated_runs' => $evaluations->count(),
                    'total_runs' => $runs->count(),
                    'recommended_model' => $version->preferred_model ?: $version->promptTemplate?->preferred_model,
                ];
            })
            ->filter(fn (array $row) => $row['average_score'] !== null)
            ->sortByDesc('average_score');
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

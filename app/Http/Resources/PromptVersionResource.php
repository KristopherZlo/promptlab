<?php

namespace App\Http\Resources;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromptVersion */
class PromptVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $evaluations = $this->whenLoaded('experimentRuns', function () {
            return $this->experimentRuns->flatMap->evaluations;
        }, collect());
        $scores = $this->whenLoaded('experimentRuns', function () {
            return $this->experimentRuns
                ->flatMap->evaluations
                ->map(fn (Evaluation $evaluation) => $evaluation->averageScore())
                ->filter();
        }, collect());
        $reviewers = $evaluations
            ->map(fn (Evaluation $evaluation) => $evaluation->evaluator?->display_name)
            ->filter()
            ->unique()
            ->values();
        $lastReviewedAt = $evaluations
            ->map(fn (Evaluation $evaluation) => $evaluation->updated_at ?? $evaluation->created_at)
            ->filter()
            ->sortDesc()
            ->first();

        $formatPassRate = $this->whenLoaded('experimentRuns', function () {
            return $this->experimentRuns->count() > 0
                ? round(($this->experimentRuns->where('format_valid', true)->count() / $this->experimentRuns->count()) * 100, 1)
                : null;
        }, $this->format_pass_rate);

        $evaluationCount = $scores->count() > 0 ? $scores->count() : ($this->evaluation_count ?? 0);
        $averageScore = $scores->isNotEmpty() ? round($scores->avg(), 2) : ($this->average_score !== null ? round((float) $this->average_score, 2) : null);
        $reviewedRuns = $this->whenLoaded('experimentRuns', fn () => $this->experimentRuns->filter(fn ($run) => $run->evaluations->isNotEmpty())->count(), $this->reviewed_runs ?? 0);
        $reviewerCount = $reviewers->count() > 0 ? $reviewers->count() : ($this->reviewer_count ?? 0);
        $reviewerNames = $reviewers->isNotEmpty() ? $reviewers->all() : ($this->reviewers ?? []);
        $lastReviewedIso = optional($lastReviewedAt ?? $this->last_reviewed_at)->toIso8601String();
        $libraryEntry = $this->relationLoaded('libraryEntry') ? $this->libraryEntry : $this->libraryEntry()->with('approver')->first();
        $isLibraryApproved = $libraryEntry !== null;

        return [
            'id' => $this->id,
            'version_label' => $this->version_label,
            'change_summary' => $this->change_summary,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->display_name),
            'system_prompt' => $this->system_prompt,
            'user_prompt_template' => $this->user_prompt_template,
            'variables_schema' => $this->variables_schema ?? [],
            'output_type' => $this->output_type,
            'output_schema_json' => $this->output_schema_json ?? [],
            'notes' => $this->notes,
            'preferred_model' => $this->preferred_model,
            'is_library_approved' => $isLibraryApproved,
            'run_count' => $this->whenLoaded('experimentRuns', fn () => $this->experimentRuns->count(), $this->run_count ?? null),
            'reviewed_runs' => $reviewedRuns,
            'evaluation_count' => $evaluationCount,
            'average_score' => $averageScore,
            'format_pass_rate' => $formatPassRate,
            'reviewer_count' => $reviewerCount,
            'reviewers' => $reviewerNames,
            'last_reviewed_at' => $lastReviewedIso,
            'library_entry' => $libraryEntry ? [
                'id' => $libraryEntry->id,
                'approved_at' => optional($libraryEntry->approved_at)->toIso8601String(),
                'approved_by' => $libraryEntry->approver?->display_name,
                'recommended_model' => $libraryEntry->recommended_model,
                'best_for' => $libraryEntry->best_for,
                'usage_notes' => $libraryEntry->usage_notes,
            ] : null,
        ];
    }
}

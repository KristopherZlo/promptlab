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
        $scores = $this->whenLoaded('experimentRuns', function () {
            return $this->experimentRuns
                ->flatMap->evaluations
                ->map(fn (Evaluation $evaluation) => $evaluation->averageScore())
                ->filter();
        }, collect());

        $formatPassRate = $this->whenLoaded('experimentRuns', function () {
            return $this->experimentRuns->count() > 0
                ? round(($this->experimentRuns->where('format_valid', true)->count() / $this->experimentRuns->count()) * 100, 1)
                : null;
        }, null);

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
            'is_library_approved' => $this->is_library_approved,
            'run_count' => $this->whenLoaded('experimentRuns', fn () => $this->experimentRuns->count()),
            'evaluation_count' => $scores->count(),
            'average_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
            'format_pass_rate' => $formatPassRate,
            'library_entry' => $this->whenLoaded('libraryEntry', fn () => [
                'id' => $this->libraryEntry?->id,
                'approved_at' => optional($this->libraryEntry?->approved_at)->toIso8601String(),
                'approved_by' => $this->libraryEntry?->approver?->display_name,
                'recommended_model' => $this->libraryEntry?->recommended_model,
                'best_for' => $this->libraryEntry?->best_for,
                'usage_notes' => $this->libraryEntry?->usage_notes,
            ]),
        ];
    }
}

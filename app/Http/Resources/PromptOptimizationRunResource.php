<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromptOptimizationRun */
class PromptOptimizationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'requested_model_name' => $this->requested_model_name,
            'budget_metric_calls' => $this->budget_metric_calls,
            'best_score' => $this->best_score !== null ? round((float) $this->best_score, 4) : null,
            'total_metric_calls' => $this->total_metric_calls,
            'candidate_count' => $this->candidate_count,
            'error_message' => $this->error_message,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'train_case_ids' => $this->train_case_ids_json ?? [],
            'validation_case_ids' => $this->validation_case_ids_json ?? [],
            'config' => $this->config_json ?? [],
            'seed_candidate' => $this->seed_candidate_json ?? [],
            'best_candidate' => $this->best_candidate_json ?? [],
            'result' => $this->result_json ?? [],
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->display_name),
            'source_version' => $this->whenLoaded('sourceVersion', fn () => [
                'id' => $this->sourceVersion?->id,
                'version_label' => $this->sourceVersion?->version_label,
                'change_summary' => $this->sourceVersion?->change_summary,
            ]),
            'derived_version' => $this->whenLoaded('derivedVersion', fn () => [
                'id' => $this->derivedVersion?->id,
                'version_label' => $this->derivedVersion?->version_label,
                'change_summary' => $this->derivedVersion?->change_summary,
            ]),
        ];
    }
}

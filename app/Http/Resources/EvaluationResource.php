<?php

namespace App\Http\Resources;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Evaluation */
class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'evaluator_id' => $this->evaluator_id,
            'evaluator_name' => $this->whenLoaded('evaluator', fn () => $this->evaluator?->display_name),
            'clarity_score' => $this->clarity_score,
            'correctness_score' => $this->correctness_score,
            'completeness_score' => $this->completeness_score,
            'tone_score' => $this->tone_score,
            'average_score' => $this->averageScore(),
            'format_valid_manual' => $this->format_valid_manual,
            'hallucination_risk' => $this->hallucination_risk,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}

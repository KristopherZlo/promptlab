<?php

namespace App\Http\Resources;

use App\Models\Evaluation;
use App\Services\AutomaticEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExperimentRun */
class ExperimentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $automaticEvaluation = $this->whenLoaded('testCase', function () {
            return app(AutomaticEvaluationService::class)->evaluateRun($this->resource);
        }, app(AutomaticEvaluationService::class)->evaluateRun($this->resource));

        $scores = $this->whenLoaded('evaluations', function () {
            return $this->evaluations
                ->map(fn (Evaluation $evaluation) => $evaluation->averageScore())
                ->filter();
        }, collect());

        return [
            'id' => $this->id,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'input_text' => $this->input_text,
            'compiled_prompt' => $this->compiled_prompt,
            'output_text' => $this->output_text,
            'output_json' => $this->output_json,
            'latency_ms' => $this->latency_ms,
            'token_input' => $this->token_input,
            'token_output' => $this->token_output,
            'format_valid' => $this->format_valid,
            'is_reviewable' => $this->isReviewable(),
            'error_message' => $this->error_message,
            'prompt_version' => $this->whenLoaded('promptVersion', fn () => [
                'id' => $this->promptVersion?->id,
                'name' => $this->promptVersion?->promptTemplate?->name,
                'version_label' => $this->promptVersion?->version_label,
                'use_case' => $this->promptVersion?->promptTemplate?->useCase?->name,
            ]),
            'test_case' => $this->whenLoaded('testCase'),
            'automatic_evaluation' => $automaticEvaluation,
            'manual_average_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
            'evaluations' => EvaluationResource::collection($this->whenLoaded('evaluations')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExperimentRun */
class ExperimentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scores = $this->whenLoaded('evaluations', function () {
            return $this->evaluations
                ->map(fn (Evaluation $evaluation) => $evaluation->averageScore())
                ->filter();
        }, collect());

        return [
            'id' => $this->id,
            'status' => $this->status,
            'input_text' => $this->input_text,
            'compiled_prompt' => $this->compiled_prompt,
            'output_text' => $this->output_text,
            'output_json' => $this->output_json,
            'latency_ms' => $this->latency_ms,
            'token_input' => $this->token_input,
            'token_output' => $this->token_output,
            'format_valid' => $this->format_valid,
            'error_message' => $this->error_message,
            'prompt_version' => $this->whenLoaded('promptVersion', fn () => [
                'id' => $this->promptVersion?->id,
                'name' => $this->promptVersion?->promptTemplate?->name,
                'version_label' => $this->promptVersion?->version_label,
                'use_case' => $this->promptVersion?->promptTemplate?->useCase?->name,
            ]),
            'test_case' => $this->whenLoaded('testCase'),
            'manual_average_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
            'evaluations' => EvaluationResource::collection($this->whenLoaded('evaluations')),
        ];
    }
}

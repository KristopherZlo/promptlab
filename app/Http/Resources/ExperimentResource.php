<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Experiment */
class ExperimentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'status' => $this->status,
            'model_name' => $this->model_name,
            'provider' => $this->provider,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'use_case' => $this->whenLoaded('useCase'),
            'summary' => $this->summary_json ?? [],
            'completed_runs' => $this->completed_runs,
            'failed_runs' => $this->failed_runs,
            'total_runs' => $this->total_runs,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'runs' => ExperimentRunResource::collection($this->whenLoaded('runs')),
        ];
    }
}

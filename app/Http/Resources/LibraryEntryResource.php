<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LibraryEntry */
class LibraryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'recommended_model' => $this->recommended_model,
            'best_for' => $this->best_for,
            'usage_notes' => $this->usage_notes,
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->display_name),
            'prompt_version' => $this->whenLoaded('promptVersion', fn () => [
                'id' => $this->promptVersion?->id,
                'version_label' => $this->promptVersion?->version_label,
                'name' => $this->promptVersion?->promptTemplate?->name,
                'use_case' => $this->promptVersion?->promptTemplate?->useCase?->name,
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromptTemplate */
class PromptTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $versions = $this->whenLoaded('versions', fn () => PromptVersionResource::collection($this->versions)->resolve(), []);
        $averageScore = collect($versions)->pluck('average_score')->filter()->avg();
        $reviewedRuns = collect($versions)->sum(fn ($version) => $version['reviewed_runs'] ?? 0);
        $reviewers = collect($versions)
            ->flatMap(fn ($version) => $version['reviewers'] ?? [])
            ->filter()
            ->unique()
            ->values();
        $lastReviewedAt = collect($versions)
            ->pluck('last_reviewed_at')
            ->filter()
            ->sortDesc()
            ->first();
        $approvedVersion = $this->relationLoaded('versions')
            ? $this->versions
                ->sortByDesc('id')
                ->first(fn ($version) => $version->libraryEntry !== null)
            : null;

        return [
            'id' => $this->id,
            'use_case_id' => $this->use_case_id,
            'name' => $this->name,
            'description' => $this->description,
            'task_type' => filled($this->task_type) ? $this->task_type : null,
            'status' => $this->status,
            'preferred_model' => $this->preferred_model,
            'tags_json' => $this->tags_json ?? [],
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->display_name),
            'use_case' => $this->whenLoaded('useCase'),
            'approval_state' => $approvedVersion ? 'approved' : 'pending',
            'approved_version_label' => $approvedVersion?->version_label,
            'approved_at' => optional($approvedVersion?->libraryEntry?->approved_at)->toIso8601String(),
            'approved_by' => $approvedVersion?->libraryEntry?->approver?->display_name,
            'versions_count' => is_array($versions) && count($versions) > 0 ? count($versions) : ($this->versions_count ?? 0),
            'latest_version_label' => is_array($versions) && count($versions) > 0 ? $versions[array_key_last($versions)]['version_label'] : ($this->latest_version_label ?? null),
            'average_score' => $averageScore !== null ? round($averageScore, 2) : ($this->average_score !== null ? round((float) $this->average_score, 2) : null),
            'reviewed_runs' => $reviewedRuns > 0 ? $reviewedRuns : ($this->reviewed_runs ?? 0),
            'reviewer_count' => $reviewers->count() > 0 ? $reviewers->count() : ($this->reviewer_count ?? 0),
            'reviewers' => $reviewers->all(),
            'last_reviewed_at' => $lastReviewedAt ?? optional($this->last_reviewed_at)->toIso8601String(),
            'versions' => $this->whenLoaded('versions', fn () => $versions),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UseCase */
class UseCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AnalyticsService $analytics */
        $analytics = app(AnalyticsService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'business_goal' => $this->business_goal,
            'primary_input_label' => $this->primary_input_label,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->display_name),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->display_name),
            'prompt_templates_count' => $this->when(isset($this->prompt_templates_count), $this->prompt_templates_count),
            'test_cases_count' => $this->when(isset($this->test_cases_count), $this->test_cases_count),
            'best_prompt' => $this->when(
                ! $this->relationLoaded('promptTemplates'),
                fn () => $analytics->bestPromptForUseCase($this->resource),
                null
            ),
            'prompt_templates' => $this->whenLoaded(
                'promptTemplates',
                fn () => PromptTemplateResource::collection($this->promptTemplates)->resolve()
            ),
            'test_cases' => $this->whenLoaded(
                'testCases',
                fn () => $this->testCases->map(fn ($testCase) => [
                    'id' => $testCase->id,
                    'title' => $testCase->title,
                    'input_text' => $testCase->input_text,
                    'expected_output' => $testCase->expected_output,
                    'expected_json' => $testCase->expected_json ?? [],
                    'variables_json' => $testCase->variables_json ?? [],
                    'metadata_json' => $testCase->metadata_json ?? [],
                    'status' => $testCase->status,
                ])->values()->all()
            ),
        ];
    }
}

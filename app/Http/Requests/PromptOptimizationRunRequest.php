<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PromptOptimizationRunRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_prompts');
    }

    public function rules(): array
    {
        $promptTemplateId = $this->route('promptTemplate')?->id;
        $maxBudget = max((int) config('gepa.max_budget_metric_calls', 60), 6);

        return [
            'source_prompt_version_id' => [
                'required',
                Rule::exists('prompt_versions', 'id')->where(function ($query) use ($promptTemplateId) {
                    $query->where('team_id', $this->currentTeamId());

                    if ($promptTemplateId) {
                        $query->where('prompt_template_id', $promptTemplateId);
                    }
                }),
            ],
            'model_name' => ['required', 'string', 'max:255', $this->allowedWorkspaceModel()],
            'budget_metric_calls' => ['required', 'integer', 'min:6', 'max:'.$maxBudget],
        ];
    }
}

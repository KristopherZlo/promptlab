<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PromptTemplateRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_prompts');
    }

    public function rules(): array
    {
        return [
            'use_case_id' => ['required', $this->teamScopedExists('use_cases')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_type' => ['required', Rule::in(['summarization', 'classification', 'rewrite', 'extraction', 'generation'])],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'preferred_model' => ['nullable', 'string', 'max:255'],
            'tags_json' => ['nullable', 'array'],
            'tags_json.*' => ['string', 'max:64'],
        ];
    }
}

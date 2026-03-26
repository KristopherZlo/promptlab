<?php

namespace App\Http\Requests;

use App\Rules\MaxEncodedSize;
use Illuminate\Validation\Rule;

class PromptTemplateRequest extends TeamAwareRequest
{
    protected function prepareForValidation(): void
    {
        $taskType = trim((string) $this->input('task_type', ''));

        $this->merge([
            'task_type' => $taskType !== '' ? $taskType : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_prompts');
    }

    public function rules(): array
    {
        return [
            'use_case_id' => ['required', $this->teamScopedExists('use_cases')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
            'preferred_model' => ['nullable', 'string', 'max:255'],
            'tags_json' => ['nullable', 'array', new MaxEncodedSize(2048)],
            'tags_json.*' => ['string', 'max:64'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PromptQuickTestRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_prompts');
    }

    public function rules(): array
    {
        return [
            'use_case_id' => ['nullable', $this->teamScopedExists('use_cases')],
            'task_type' => ['required', Rule::in(['summarization', 'classification', 'rewrite', 'extraction', 'generation'])],
            'model_name' => ['required', 'string', 'max:255', $this->allowedWorkspaceModel()],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:64', 'max:4096'],
            'system_prompt' => ['nullable', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'variables_schema' => ['nullable', 'array'],
            'variables_schema.*.name' => ['required_with:variables_schema', 'string', 'max:64'],
            'variables_schema.*.label' => ['nullable', 'string', 'max:255'],
            'variables_schema.*.required' => ['nullable', 'boolean'],
            'variables_schema.*.default' => ['nullable'],
            'variables' => ['nullable', 'array'],
            'output_type' => ['required', Rule::in(['text', 'json'])],
            'output_schema_json' => ['nullable', 'array'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}

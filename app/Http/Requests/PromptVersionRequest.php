<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PromptVersionRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_prompts');
    }

    public function rules(): array
    {
        return [
            'version_label' => ['nullable', 'string', 'max:64'],
            'change_summary' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'variables_schema' => ['nullable', 'array'],
            'variables_schema.*.name' => ['required_with:variables_schema', 'string', 'max:64'],
            'variables_schema.*.label' => ['nullable', 'string', 'max:255'],
            'variables_schema.*.required' => ['nullable', 'boolean'],
            'variables_schema.*.default' => ['nullable'],
            'output_type' => ['required', Rule::in(['text', 'json'])],
            'output_schema_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
            'is_library_approved' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Rules\MaxEncodedSize;
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
            'system_prompt' => ['nullable', 'string', 'max:12000'],
            'user_prompt_template' => ['required', 'string', 'max:20000'],
            'variables_schema' => ['nullable', 'array', new MaxEncodedSize(16000)],
            'variables_schema.*.name' => ['required_with:variables_schema', 'string', 'max:64'],
            'variables_schema.*.label' => ['nullable', 'string', 'max:255'],
            'variables_schema.*.required' => ['nullable', 'boolean'],
            'variables_schema.*.default' => ['nullable'],
            'output_type' => ['required', Rule::in(['text', 'json'])],
            'output_schema_json' => ['nullable', 'array', new MaxEncodedSize(12000)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}

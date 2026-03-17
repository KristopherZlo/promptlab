<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TestCaseRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_test_cases');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'input_text' => ['required', 'string'],
            'expected_output' => ['nullable', 'string'],
            'expected_json' => ['nullable', 'array'],
            'variables_json' => ['nullable', 'array'],
            'metadata_json' => ['nullable', 'array'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
        ];
    }
}

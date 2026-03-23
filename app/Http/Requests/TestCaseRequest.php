<?php

namespace App\Http\Requests;

use App\Rules\MaxEncodedSize;
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
            'input_text' => ['required', 'string', 'max:12000'],
            'expected_output' => ['nullable', 'string', 'max:12000'],
            'expected_json' => ['nullable', 'array', new MaxEncodedSize(12000)],
            'variables_json' => ['nullable', 'array', new MaxEncodedSize(12000)],
            'metadata_json' => ['nullable', 'array', new MaxEncodedSize(12000)],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExperimentRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('run_experiments');
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['single', 'compare', 'batch'])],
            'prompt_version_ids' => ['required', 'array', 'min:1', 'max:3'],
            'prompt_version_ids.*' => ['required', 'integer', $this->teamScopedExists('prompt_versions')],
            'input_text' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'test_case_ids' => ['nullable', 'array', 'max:50'],
            'test_case_ids.*' => ['integer', $this->teamScopedExists('test_cases')],
            'model_name' => ['required', 'string', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:64', 'max:4096'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();
        $this->assertExperimentShape($validated);

        return data_get($validated, $key, $default);
    }

    private function assertExperimentShape(array $validated): void
    {
        $promptCount = count($validated['prompt_version_ids']);

        if ($validated['mode'] === 'single' && $promptCount !== 1) {
            throw ValidationException::withMessages([
                'prompt_version_ids' => 'Single mode requires exactly one prompt version.',
            ]);
        }

        if ($validated['mode'] === 'compare' && ($promptCount < 2 || $promptCount > 3)) {
            throw ValidationException::withMessages([
                'prompt_version_ids' => 'Compare mode requires two or three prompt versions.',
            ]);
        }

        if ($validated['mode'] === 'batch' && $promptCount !== 1) {
            throw ValidationException::withMessages([
                'prompt_version_ids' => 'Batch mode requires exactly one prompt version.',
            ]);
        }

        if ($validated['mode'] !== 'batch' && blank($validated['input_text'] ?? null)) {
            throw ValidationException::withMessages([
                'input_text' => 'Input text is required for single and compare runs.',
            ]);
        }

        if ($validated['mode'] === 'batch' && empty($validated['test_case_ids'] ?? [])) {
            throw ValidationException::withMessages([
                'test_case_ids' => 'Batch mode requires at least one saved test case.',
            ]);
        }
    }
}

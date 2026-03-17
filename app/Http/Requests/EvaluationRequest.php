<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class EvaluationRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('evaluate_runs');
    }

    public function rules(): array
    {
        return [
            'experiment_run_id' => ['required', $this->teamScopedExists('experiment_runs')],
            'clarity_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'correctness_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'completeness_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'tone_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'format_valid_manual' => ['nullable', 'boolean'],
            'hallucination_risk' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}

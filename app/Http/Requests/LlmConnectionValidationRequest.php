<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LlmConnectionValidationRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_connections');
    }

    public function rules(): array
    {
        return [
            'connection_id' => ['nullable', 'integer', $this->teamScopedExists('llm_connections')],
            'driver' => ['required', Rule::in(['openai'])],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string'],
        ];
    }
}

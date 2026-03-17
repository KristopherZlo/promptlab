<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LlmConnectionRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_connections');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::in(['openai'])],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'models_json' => ['required', 'array', 'min:1'],
            'models_json.*' => ['string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}

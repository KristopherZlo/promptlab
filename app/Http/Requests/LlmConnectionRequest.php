<?php

namespace App\Http\Requests;

use App\Services\ConnectionBaseUrlPolicy;
use Illuminate\Validation\Rule;

class LlmConnectionRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_connections');
    }

    public function rules(): array
    {
        $baseUrls = app(ConnectionBaseUrlPolicy::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::in(['openai', 'anthropic'])],
            'base_url' => [
                'nullable',
                'url',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($baseUrls): void {
                    if (! $baseUrls->isAllowed((string) $this->input('driver'), is_string($value) ? $value : null)) {
                        $fail($baseUrls->message((string) $this->input('driver')));
                    }
                },
            ],
            'api_key' => ['nullable', 'string'],
            'models_json' => ['required', 'array', 'min:1'],
            'models_json.*' => ['string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}

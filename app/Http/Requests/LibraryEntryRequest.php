<?php

namespace App\Http\Requests;

class LibraryEntryRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_library');
    }

    public function rules(): array
    {
        return [
            'prompt_version_id' => ['required', $this->teamScopedExists('prompt_versions')],
            'recommended_model' => ['nullable', 'string', 'max:255'],
            'best_for' => ['nullable', 'string', 'max:255'],
            'usage_notes' => ['nullable', 'string'],
        ];
    }
}

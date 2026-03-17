<?php

namespace App\Http\Requests;

use App\Models\UseCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UseCaseRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_use_cases');
    }

    public function rules(): array
    {
        /** @var UseCase|null $useCase */
        $useCase = $this->route('useCase');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('use_cases', 'slug')
                    ->ignore($useCase?->id)
                    ->where(fn ($query) => $query->where('team_id', $this->currentTeamId())),
            ],
            'description' => ['nullable', 'string'],
            'business_goal' => ['nullable', 'string'],
            'primary_input_label' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }

        if ($this->filled('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }
    }
}

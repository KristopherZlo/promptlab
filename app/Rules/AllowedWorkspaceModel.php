<?php

namespace App\Rules;

use App\Services\AllowedModelService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedWorkspaceModel implements ValidationRule
{
    public function __construct(
        private readonly AllowedModelService $allowedModels,
        private readonly ?int $teamId,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->allowedModels->isAllowed($value, $this->teamId)) {
            $fail('Select a configured model that is available in this workspace.');
        }
    }
}

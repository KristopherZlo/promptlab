<?php

namespace App\Services;

class AllowedModelService
{
    public function __construct(
        private readonly LLMProviderManager $providers,
    ) {
    }

    public function isAllowed(string $model, ?int $teamId = null): bool
    {
        return in_array($model, $this->allowedValues($teamId), true);
    }

    public function allowedValues(?int $teamId = null): array
    {
        return collect($this->providers->availableModels($teamId))
            ->filter(fn (array $model) => (bool) ($model['available'] ?? false) || str_starts_with((string) ($model['value'] ?? ''), 'mock:'))
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && filled($value))
            ->values()
            ->all();
    }
}

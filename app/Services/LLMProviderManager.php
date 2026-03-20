<?php

namespace App\Services;

use App\Exceptions\TerminalOperationException;
use App\Models\LlmConnection;
use App\Services\ModelProviders\Contracts\LLMProvider;
use App\Services\ModelProviders\AnthropicProvider;
use App\Services\ModelProviders\MockProvider;
use App\Services\ModelProviders\OpenAIProvider;
use Illuminate\Support\Collection;
use RuntimeException;

class LLMProviderManager
{
    public function __construct(
        private readonly MockProvider $mockProvider,
        private readonly OpenAIProvider $openAiProvider,
        private readonly AnthropicProvider $anthropicProvider,
    ) {
    }

    public function availableModels(?int $teamId = null): array
    {
        $configured = collect(config('llm.models', []));
        $teamModels = $this->teamConnectionModels($teamId);

        return $configured
            ->concat($teamModels)
            ->unique('value')
            ->values()
            ->all();
    }

    public function driverForModel(string $model, ?int $teamId = null): string
    {
        $configured = collect($this->availableModels($teamId))
            ->firstWhere('value', $model);

        if ($configured) {
            return (string) $configured['driver'];
        }

        throw new TerminalOperationException('The selected model is not available in this workspace.');
    }

    public function runPrompt(string $compiledPrompt, array $options): array
    {
        $resolved = $this->resolveRuntimeOptions((string) $options['model'], $options);
        $provider = $this->provider($resolved['driver']);

        return $provider->runPrompt($compiledPrompt, $resolved['options']);
    }

    public function validateConnection(string $driver, array $options = []): array
    {
        return $this->provider($driver)->validateConnection($options);
    }

    public function discoverModels(string $driver, array $options = []): array
    {
        return $this->provider($driver)->discoverModels($options);
    }

    private function provider(string $driver): LLMProvider
    {
        return match ($driver) {
            'mock' => $this->mockProvider,
            'openai' => $this->openAiProvider,
            'anthropic' => $this->anthropicProvider,
            default => throw new RuntimeException("Unsupported provider driver [{$driver}]."),
        };
    }

    private function teamConnectionModels(?int $teamId = null): Collection
    {
        $query = $teamId !== null
            ? LlmConnection::withoutGlobalScopes()->where('team_id', $teamId)
            : LlmConnection::query();

        return $query
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->flatMap(function (LlmConnection $connection): array {
                $models = collect($connection->models_json ?? [])->filter()->values();

                return $models->map(fn (string $model) => [
                    'value' => "{$connection->driver}:team:{$connection->id}:{$model}",
                    'label' => "{$connection->name} / {$model}",
                    'driver' => $connection->driver,
                    'available' => filled($connection->api_key),
                    'connection_id' => $connection->id,
                    'team_id' => $connection->team_id,
                    'source' => 'team',
                ])->all();
            });
    }

    private function resolveRuntimeOptions(string $model, array $options): array
    {
        if (preg_match('/^([a-z0-9_-]+):team:(\d+):(.+)$/', $model, $matches) === 1) {
            $connection = LlmConnection::withoutGlobalScopes()->find((int) $matches[2]);
            $teamId = isset($options['team_id']) ? (int) $options['team_id'] : null;

            if (! $connection || ! $connection->is_active) {
                throw new TerminalOperationException('The selected AI connection is not available for this workspace.');
            }

            if (! filled($connection->api_key)) {
                throw new TerminalOperationException('The selected AI connection has no API key.');
            }

            if ($teamId !== null && $teamId > 0 && $connection->team_id !== $teamId) {
                throw new TerminalOperationException('The selected AI connection does not belong to this workspace.');
            }

            if ($connection->driver !== $matches[1]) {
                throw new TerminalOperationException('The selected AI connection is not compatible with this provider.');
            }

            if (! collect($connection->models_json ?? [])->contains($matches[3])) {
                throw new TerminalOperationException('The selected AI connection no longer exposes this model.');
            }

            return [
                'driver' => $connection->driver,
                'options' => [
                    ...$options,
                    'model' => $matches[3],
                    'api_key' => $connection->api_key,
                    'base_url' => $connection->base_url ?: $this->defaultBaseUrl($connection->driver),
                    'connection_id' => $connection->id,
                    'connection_name' => $connection->name,
                ],
            ];
        }

        return [
            'driver' => $this->driverForModel($model, isset($options['team_id']) ? (int) $options['team_id'] : null),
            'options' => $options,
        ];
    }

    private function defaultBaseUrl(string $driver): ?string
    {
        return match ($driver) {
            'openai' => config('services.openai.base_url'),
            'anthropic' => config('services.anthropic.base_url'),
            default => null,
        };
    }
}

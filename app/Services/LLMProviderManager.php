<?php

namespace App\Services;

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

    public function availableModels(): array
    {
        $configured = collect(config('llm.models', []));
        $teamModels = $this->teamConnectionModels();

        return $configured
            ->concat($teamModels)
            ->unique('value')
            ->values()
            ->all();
    }

    public function driverForModel(string $model): string
    {
        $configured = collect(config('llm.models', []))
            ->firstWhere('value', $model);

        if ($configured) {
            return $configured['driver'];
        }

        return str_contains($model, ':') ? explode(':', $model, 2)[0] : 'mock';
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

    private function teamConnectionModels(): Collection
    {
        return LlmConnection::query()
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
                    'source' => 'team',
                ])->all();
            });
    }

    private function resolveRuntimeOptions(string $model, array $options): array
    {
        if (preg_match('/^([a-z0-9_-]+):team:(\d+):(.+)$/', $model, $matches) === 1) {
            $connection = LlmConnection::query()->find((int) $matches[2]);

            if (! $connection || ! $connection->is_active) {
                throw new RuntimeException('The selected AI connection is not available for this team.');
            }

            if (! filled($connection->api_key)) {
                throw new RuntimeException('The selected AI connection has no API key.');
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
            'driver' => $this->driverForModel($model),
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

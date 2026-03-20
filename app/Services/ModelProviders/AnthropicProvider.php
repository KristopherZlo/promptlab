<?php

namespace App\Services\ModelProviders;

use App\Services\ModelProviders\Contracts\LLMProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AnthropicProvider implements LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $systemPrompt = trim(implode("\n\n", array_filter([
            (string) ($options['system_prompt'] ?? ''),
            ($options['output_type'] ?? 'text') === 'json' ? 'Return only valid JSON that matches the requested schema.' : '',
        ])));

        $payload = [
            'model' => $this->resolvedModelName((string) ($options['model'] ?? 'claude-sonnet-4-5')),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => (string) ($options['user_prompt'] ?? $compiledPrompt),
                ],
            ],
            'temperature' => (float) ($options['temperature'] ?? 0.2),
            'max_tokens' => (int) ($options['max_tokens'] ?? 600),
        ];

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $startedAt = microtime(true);

        $response = $this->request($baseUrl, $apiKey)
            ->post($baseUrl.'/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic request failed: '.$this->errorMessage($response));
        }

        $body = $response->json();

        return [
            'output_text' => $this->textContent($body),
            'model_name' => (string) data_get($body, 'model', $payload['model']),
            'token_input' => (int) data_get($body, 'usage.input_tokens', 0),
            'token_output' => (int) data_get($body, 'usage.output_tokens', 0),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'raw_response' => $body,
        ];
    }

    public function validateConnection(array $options = []): array
    {
        $models = $this->discoverModels($options);

        return [
            'reachable' => true,
            'message' => count($models) > 0
                ? 'Connection verified and Claude models were discovered.'
                : 'Connection verified, but the Anthropic API returned no models.',
            'models' => $models,
        ];
    }

    public function discoverModels(array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $response = $this->request($baseUrl, $apiKey)
            ->get($baseUrl.'/models');

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic validation failed: '.$this->errorMessage($response));
        }

        return collect($response->json('data', []))
            ->map(fn ($item) => data_get($item, 'id'))
            ->filter(fn ($model) => filled($model) && is_string($model))
            ->map(fn (string $model) => trim($model))
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    private function resolvedModelName(string $model): string
    {
        return str_contains($model, ':') ? explode(':', $model, 2)[1] : $model;
    }

    private function connectionConfig(array $options): array
    {
        $apiKey = $options['api_key'] ?? config('services.anthropic.api_key');
        $baseUrl = rtrim((string) ($options['base_url'] ?? config('services.anthropic.base_url')), '/');

        if (! $apiKey) {
            throw new RuntimeException('An API key is required to validate this Anthropic connection.');
        }

        if (blank($baseUrl)) {
            throw new RuntimeException('A base URL is required to validate this Anthropic connection.');
        }

        return [$apiKey, $baseUrl];
    }

    private function request(string $baseUrl, string $apiKey)
    {
        return Http::timeout(60)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->baseUrl($baseUrl);
    }

    private function errorMessage(Response $response): string
    {
        $jsonMessage = data_get($response->json(), 'error.message');

        if (filled($jsonMessage) && is_string($jsonMessage)) {
            return $jsonMessage;
        }

        return Str::limit(trim($response->body()), 220, '...');
    }

    private function textContent(array $body): string
    {
        return collect($body['content'] ?? [])
            ->filter(fn ($item) => data_get($item, 'type') === 'text')
            ->map(fn ($item) => (string) data_get($item, 'text', ''))
            ->filter()
            ->implode("\n\n");
    }
}

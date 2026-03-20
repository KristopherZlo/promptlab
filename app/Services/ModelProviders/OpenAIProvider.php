<?php

namespace App\Services\ModelProviders;

use App\Services\ModelProviders\Contracts\LLMProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAIProvider implements LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $messages = array_values(array_filter([
            filled($options['system_prompt'] ?? null)
                ? ['role' => 'system', 'content' => (string) $options['system_prompt']]
                : null,
            [
                'role' => 'user',
                'content' => (string) ($options['user_prompt'] ?? $compiledPrompt),
            ],
        ]));

        $payload = [
            'model' => $this->resolvedModelName((string) ($options['model'] ?? 'openai:gpt-4.1-mini')),
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.2),
        ];
        $maxTokens = (int) ($options['max_tokens'] ?? 600);
        $payload['max_completion_tokens'] = $maxTokens;

        if (($options['output_type'] ?? 'text') === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startedAt = microtime(true);

        $response = $this->sendChatCompletionRequest($baseUrl, $apiKey, $payload);

        if ($this->shouldRetryWithLegacyMaxTokens($response)) {
            unset($payload['max_completion_tokens']);
            $payload['max_tokens'] = $maxTokens;
            $response = $this->sendChatCompletionRequest($baseUrl, $apiKey, $payload);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        $body = $response->json();

        return [
            'output_text' => (string) data_get($body, 'choices.0.message.content', ''),
            'model_name' => (string) data_get($body, 'model', $payload['model']),
            'token_input' => (int) data_get($body, 'usage.prompt_tokens', 0),
            'token_output' => (int) data_get($body, 'usage.completion_tokens', 0),
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
                ? 'Connection verified and available models were discovered.'
                : 'Connection verified, but the provider did not return any models.',
            'models' => $models,
        ];
    }

    public function discoverModels(array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->get($baseUrl.'/models');

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI validation failed: '.$this->errorMessage($response));
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
        $apiKey = $options['api_key'] ?? config('services.openai.api_key');
        $baseUrl = rtrim((string) ($options['base_url'] ?? config('services.openai.base_url')), '/');

        if (! $apiKey) {
            throw new RuntimeException('An API key is required to validate this connection.');
        }

        if (blank($baseUrl)) {
            throw new RuntimeException('A base URL is required to validate this connection.');
        }

        return [$apiKey, $baseUrl];
    }

    private function errorMessage(Response $response): string
    {
        $jsonMessage = data_get($response->json(), 'error.message');

        if (filled($jsonMessage) && is_string($jsonMessage)) {
            return $jsonMessage;
        }

        return Str::limit(trim($response->body()), 220, '...');
    }

    private function sendChatCompletionRequest(string $baseUrl, string $apiKey, array $payload): Response
    {
        return Http::timeout(60)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $payload);
    }

    private function shouldRetryWithLegacyMaxTokens(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        $parameter = data_get($response->json(), 'error.param');
        $message = (string) data_get($response->json(), 'error.message', '');

        return $parameter === 'max_completion_tokens'
            || str_contains($message, 'max_completion_tokens');
    }
}

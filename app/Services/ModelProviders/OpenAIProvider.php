<?php

namespace App\Services\ModelProviders;

use App\Exceptions\RetryableOperationException;
use App\Exceptions\TerminalOperationException;
use App\Services\ModelProviders\Contracts\LLMProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAIProvider implements LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);
        $resolvedModel = $this->resolvedModelName((string) ($options['model'] ?? 'openai:gpt-5.2'));

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
            'model' => $resolvedModel,
            'messages' => $messages,
        ];

        if ($this->supportsChatCompletionTemperature($resolvedModel)) {
            $payload['temperature'] = (float) ($options['temperature'] ?? 0.2);
        }

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
            $this->throwForFailedResponse('OpenAI request failed', $response);
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
        try {
            $models = $this->discoverModels($options);

            return [
                'reachable' => true,
                'message' => count($models) > 0
                    ? 'Connection verified and available models were discovered.'
                    : 'Connection verified, but the provider did not return any models.',
                'models' => $models,
            ];
        } catch (\Throwable $error) {
            $providedModels = collect($options['models_json'] ?? [])
                ->filter(fn ($model) => is_string($model) && filled($model))
                ->map(fn (string $model) => trim($model))
                ->filter()
                ->values()
                ->all();

            if (count($providedModels) === 0) {
                throw $error;
            }

            $this->probeModel($providedModels[0], $options);

            return [
                'reachable' => true,
                'message' => 'Connection verified with the selected model. This provider does not expose model listing through /models.',
                'models' => $providedModels,
            ];
        }
    }

    public function discoverModels(array $options = []): array
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->get($baseUrl.'/models');

        if (! $response->successful()) {
            $this->throwForFailedResponse('OpenAI validation failed', $response);
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
            throw new TerminalOperationException('An API key is required to validate this connection.');
        }

        if (blank($baseUrl)) {
            throw new TerminalOperationException('A base URL is required to validate this connection.');
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
        try {
            return Http::timeout(60)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new RetryableOperationException(
                'OpenAI connection failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function probeModel(string $model, array $options): void
    {
        [$apiKey, $baseUrl] = $this->connectionConfig($options);

        $payload = [
            'model' => $this->resolvedModelName($model),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'ping',
                ],
            ],
            'temperature' => 0,
        ];
        $maxTokens = 16;
        $payload['max_completion_tokens'] = $maxTokens;

        $response = $this->sendChatCompletionRequest($baseUrl, $apiKey, $payload);

        if ($this->shouldRetryWithLegacyMaxTokens($response)) {
            unset($payload['max_completion_tokens']);
            $payload['max_tokens'] = $maxTokens;
            $response = $this->sendChatCompletionRequest($baseUrl, $apiKey, $payload);
        }

        if (! $response->successful()) {
            $this->throwForFailedResponse('OpenAI validation failed', $response);
        }
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

    private function supportsChatCompletionTemperature(string $model): bool
    {
        return ! in_array($model, [
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-5-chat-latest',
        ], true);
    }

    private function throwForFailedResponse(string $prefix, Response $response): never
    {
        $message = $prefix.': '.$this->errorMessage($response);
        $status = $response->status();

        if ($status === 429 || $status >= 500) {
            throw new RetryableOperationException($message);
        }

        throw new TerminalOperationException($message);
    }
}

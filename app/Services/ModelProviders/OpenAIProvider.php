<?php

namespace App\Services\ModelProviders;

use App\Services\ModelProviders\Contracts\LLMProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements LLMProvider
{
    public function runPrompt(string $compiledPrompt, array $options = []): array
    {
        $apiKey = $options['api_key'] ?? config('services.openai.api_key');
        $baseUrl = rtrim((string) ($options['base_url'] ?? config('services.openai.base_url')), '/');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

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
            'max_tokens' => (int) ($options['max_tokens'] ?? 600),
        ];

        if (($options['output_type'] ?? 'text') === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startedAt = microtime(true);

        $response = Http::timeout(60)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $payload);

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

    private function resolvedModelName(string $model): string
    {
        return str_contains($model, ':') ? explode(':', $model, 2)[1] : $model;
    }
}

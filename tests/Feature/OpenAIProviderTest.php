<?php

namespace Tests\Feature;

use App\Services\ModelProviders\OpenAIProvider;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function test_openai_provider_prefers_max_completion_tokens(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'model' => 'gpt-4.1-mini',
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from OpenAI.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 6,
                ],
            ]),
        ]);

        $response = app(OpenAIProvider::class)->runPrompt('Say hello.', [
            'api_key' => 'sk-test-123',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'openai:gpt-4.1-mini',
            'temperature' => 0.2,
            'max_tokens' => 321,
        ]);

        $this->assertSame('Hello from OpenAI.', $response['output_text']);

        Http::assertSent(function (ClientRequest $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && ($data['max_completion_tokens'] ?? null) === 321
                && ! array_key_exists('max_tokens', $data);
        });
    }

    public function test_openai_provider_falls_back_to_legacy_max_tokens_when_needed(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => "Unsupported parameter: 'max_completion_tokens' is not supported with this model. Use 'max_tokens' instead.",
                        'param' => 'max_completion_tokens',
                        'type' => 'invalid_request_error',
                        'code' => 'unsupported_parameter',
                    ],
                ], 400)
                ->push([
                    'model' => 'legacy-model',
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Fallback succeeded.',
                            ],
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => 18,
                        'completion_tokens' => 7,
                    ],
                ], 200),
        ]);

        $response = app(OpenAIProvider::class)->runPrompt('Say hello.', [
            'api_key' => 'sk-test-legacy',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'openai:legacy-model',
            'temperature' => 0.2,
            'max_tokens' => 111,
        ]);

        $this->assertSame('Fallback succeeded.', $response['output_text']);

        Http::assertSentCount(2);

        $requests = Http::recorded()->map(fn (array $entry) => $entry[0]->data())->values();

        $this->assertSame(111, $requests[0]['max_completion_tokens'] ?? null);
        $this->assertArrayNotHasKey('max_tokens', $requests[0]);
        $this->assertSame(111, $requests[1]['max_tokens'] ?? null);
        $this->assertArrayNotHasKey('max_completion_tokens', $requests[1]);
    }
}

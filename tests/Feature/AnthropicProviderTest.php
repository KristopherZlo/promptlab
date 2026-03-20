<?php

namespace Tests\Feature;

use App\Services\ModelProviders\AnthropicProvider;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    public function test_anthropic_provider_runs_prompt(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'model' => 'claude-sonnet-4-5',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello from Claude.',
                    ],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ]),
        ]);

        $response = app(AnthropicProvider::class)->runPrompt('Say hello.', [
            'api_key' => 'claude-test-key',
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => 'anthropic:claude-sonnet-4-5',
        ]);

        $this->assertSame('Hello from Claude.', $response['output_text']);

        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'claude-test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && ($request->data()['model'] ?? null) === 'claude-sonnet-4-5'
        );
    }

    public function test_anthropic_provider_discovers_models(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-sonnet-4-5'],
                    ['id' => 'claude-haiku-4-5'],
                ],
            ]),
        ]);

        $models = app(AnthropicProvider::class)->discoverModels([
            'api_key' => 'claude-test-key',
            'base_url' => 'https://api.anthropic.com/v1',
        ]);

        $this->assertSame(['claude-haiku-4-5', 'claude-sonnet-4-5'], $models);
    }
}

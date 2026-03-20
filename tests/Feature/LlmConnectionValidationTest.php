<?php

namespace Tests\Feature;

use App\Models\LlmConnection;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmConnectionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_admin_can_validate_openai_connection_before_saving(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4.1'],
                    ['id' => 'gpt-4.1-mini'],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Validation Team',
            'description' => 'Workspace for connection validation.',
        ]);

        $this->actingAs($user)
            ->post(route('api.llm-connections.validate'), [
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.reachable', true)
            ->assertJsonPath('data.models.0', 'gpt-4.1')
            ->assertJsonPath('data.models.1', 'gpt-4.1-mini');

        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'https://api.openai.com/v1/models'
            && $request->hasHeader('Authorization', 'Bearer sk-test-123')
        );
    }

    public function test_connection_validation_can_use_stored_api_key_for_existing_connection(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4.1-mini'],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Existing Connection Team',
            'description' => 'Workspace for edit validation.',
        ]);

        $connection = LlmConnection::create([
            'team_id' => $team->id,
            'name' => 'OpenAI Primary',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'stored-secret-key',
            'models_json' => ['gpt-4.1-mini'],
            'is_active' => true,
            'is_default' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('api.llm-connections.validate'), [
                'connection_id' => $connection->id,
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.models.0', 'gpt-4.1-mini');

        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'https://api.openai.com/v1/models'
            && $request->hasHeader('Authorization', 'Bearer stored-secret-key')
        );
    }

    public function test_team_admin_can_validate_anthropic_connection_before_saving(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-sonnet-4-5'],
                    ['id' => 'claude-haiku-4-5'],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Anthropic Validation Team',
            'description' => 'Workspace for Claude validation.',
        ]);

        $this->actingAs($user)
            ->post(route('api.llm-connections.validate'), [
                'driver' => 'anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'api_key' => 'claude-key-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.reachable', true)
            ->assertJsonPath('data.models.0', 'claude-haiku-4-5')
            ->assertJsonPath('data.models.1', 'claude-sonnet-4-5');

        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'https://api.anthropic.com/v1/models'
            && $request->hasHeader('x-api-key', 'claude-key-123')
            && $request->hasHeader('anthropic-version', '2023-06-01')
        );
    }
}

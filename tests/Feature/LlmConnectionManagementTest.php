<?php

namespace Tests\Feature;

use App\Models\LlmConnection;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LlmConnectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_api_keys_are_encrypted_at_rest_and_hidden_from_api_responses(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Secure Connections Team',
            'description' => 'Workspace for connection storage checks.',
        ]);

        $response = $this->actingAs($user)
            ->post(route('api.llm-connections.store'), [
                'name' => 'OpenAI Primary',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-live-secret',
                'models_json' => ['gpt-5.2'],
                'is_active' => true,
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.api_key');

        $connection = LlmConnection::query()->findOrFail($response->json('data.id'));
        $rawApiKey = DB::table('llm_connections')
            ->where('id', $connection->id)
            ->value('api_key');

        $this->assertNotNull($rawApiKey);
        $this->assertNotSame('sk-live-secret', $rawApiKey);
        $this->assertSame('sk-live-secret', $connection->fresh()->api_key);
    }

    public function test_connection_store_rejects_unapproved_base_urls(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Connection URL Guardrails',
            'description' => 'Workspace for base URL restrictions.',
        ]);

        $this->actingAs($user)
            ->postJson(route('api.llm-connections.store'), [
                'name' => 'Suspicious OpenAI Proxy',
                'driver' => 'openai',
                'base_url' => 'https://attacker.example/v1',
                'api_key' => 'sk-live-secret',
                'models_json' => ['gpt-5.2'],
                'is_active' => true,
                'is_default' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_url']);

        $this->assertDatabaseCount('llm_connections', 0);
    }
}

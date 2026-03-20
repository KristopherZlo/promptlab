<?php

namespace Tests\Feature;

use App\Models\LlmConnection;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TestCase as PromptTestCase;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_test_rejects_unknown_model_names(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Quick Test Lockdown',
            'description' => 'Workspace for model validation.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Validation',
            'slug' => 'validation',
            'description' => 'Quick test validation fixture.',
            'business_goal' => 'Reject forged models.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.prompts.quick-test'), [
                'use_case_id' => $useCase->id,
                'task_type' => 'summarization',
                'model_name' => 'openai:not-allowed',
                'temperature' => 0.2,
                'max_tokens' => 500,
                'system_prompt' => 'Summarize clearly.',
                'user_prompt_template' => 'Summarize {{input_text}}',
                'variables_schema' => [],
                'variables' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'input_text' => 'Reject this model.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['model_name']);
    }

    public function test_prompt_optimization_rejects_foreign_workspace_connection_models(): void
    {
        [$user, $team, $template, $version] = $this->optimizationFixture();

        $foreignOwner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $foreignTeam = app(TeamProvisioningService::class)->createTeam($foreignOwner, [
            'name' => 'Foreign Workspace',
            'description' => 'Other workspace connection.',
        ]);

        $foreignConnection = LlmConnection::create([
            'team_id' => $foreignTeam->id,
            'name' => 'Foreign OpenAI',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-foreign',
            'models_json' => ['gpt-5.2'],
            'is_active' => true,
            'is_default' => true,
            'created_by' => $foreignOwner->id,
            'updated_by' => $foreignOwner->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.prompt-optimizations.store', $template), [
                'source_prompt_version_id' => $version->id,
                'model_name' => "openai:team:{$foreignConnection->id}:gpt-5.2",
                'budget_metric_calls' => 12,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['model_name']);
    }

    public function test_experiments_reject_inactive_connection_models(): void
    {
        [$user, $team, , $version] = $this->optimizationFixture();

        $connection = LlmConnection::create([
            'team_id' => $team->id,
            'name' => 'Disabled OpenAI',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-disabled',
            'models_json' => ['gpt-5.2'],
            'is_active' => false,
            'is_default' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.experiments.store'), [
                'mode' => 'single',
                'prompt_version_ids' => [$version->id],
                'input_text' => 'Reject disabled connection.',
                'variables' => [],
                'model_name' => "openai:team:{$connection->id}:gpt-5.2",
                'temperature' => 0.2,
                'max_tokens' => 500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['model_name']);
    }

    private function optimizationFixture(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Optimization Lockdown Team',
            'description' => 'Workspace for model validation.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Optimization',
            'slug' => 'optimization',
            'description' => 'Optimization fixture.',
            'business_goal' => 'Validate model ownership.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Optimization Prompt',
            'description' => 'Model lockdown fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Initial version.',
            'system_prompt' => 'Summarize clearly.',
            'user_prompt_template' => 'Summarize {{input_text}}',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PromptTestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'Expected output',
            'input_text' => 'Summarize this sample.',
            'expected_output' => 'sample',
            'expected_json' => [],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        return [$user, $team, $template, $version];
    }
}

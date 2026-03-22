<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEndpointThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_test_endpoint_is_rate_limited(): void
    {
        [$user, $useCase] = $this->promptFixture('Quick Test Throttle Team');

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($user)
                ->postJson(route('api.prompts.quick-test'), [
                    'use_case_id' => $useCase->id,
                    'task_type' => 'summarization',
                    'model_name' => 'mock:team-lab-v1',
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                    'system_prompt' => 'Summarize clearly.',
                    'user_prompt_template' => 'Summarize {{input_text}}',
                    'variables_schema' => [],
                    'variables' => [],
                    'output_type' => 'text',
                    'output_schema_json' => [],
                    'input_text' => 'Throttle fixture input.',
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson(route('api.prompts.quick-test'), [
                'use_case_id' => $useCase->id,
                'task_type' => 'summarization',
                'model_name' => 'mock:team-lab-v1',
                'temperature' => 0.2,
                'max_tokens' => 500,
                'system_prompt' => 'Summarize clearly.',
                'user_prompt_template' => 'Summarize {{input_text}}',
                'variables_schema' => [],
                'variables' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'input_text' => 'Throttle fixture input.',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }

    public function test_experiment_creation_endpoint_is_rate_limited(): void
    {
        [$user, $useCase] = $this->promptFixture('Experiment Throttle Team');

        $template = PromptTemplate::create([
            'team_id' => $useCase->team_id,
            'use_case_id' => $useCase->id,
            'name' => 'Throttle experiment prompt',
            'description' => 'Experiment throttling fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['security'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $useCase->team_id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Baseline.',
            'system_prompt' => 'Summarize clearly.',
            'user_prompt_template' => 'Summarize {{input_text}}',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->actingAs($user)
                ->postJson(route('api.experiments.store'), [
                    'mode' => 'single',
                    'prompt_version_ids' => [$version->id],
                    'input_text' => 'Throttle experiment run.',
                    'variables' => [],
                    'model_name' => 'mock:team-lab-v1',
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson(route('api.experiments.store'), [
                'mode' => 'single',
                'prompt_version_ids' => [$version->id],
                'input_text' => 'Throttle experiment run.',
                'variables' => [],
                'model_name' => 'mock:team-lab-v1',
                'temperature' => 0.2,
                'max_tokens' => 500,
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests.');
    }

    private function promptFixture(string $teamName): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => $teamName,
            'description' => 'Workspace for AI endpoint throttling.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Throttle fixture',
            'slug' => strtolower(str_replace(' ', '-', $teamName)),
            'description' => 'Throttle fixture.',
            'business_goal' => 'Limit expensive requests.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$user, $useCase];
    }
}

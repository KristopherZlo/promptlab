<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayloadLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_version_creation_rejects_oversized_prompt_bodies(): void
    {
        [$user, $team, $useCase] = $this->promptFixture('Payload Prompt Team');

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Payload fixture prompt',
            'description' => 'Prompt version payload limit fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['security'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.prompt-versions.store', $template), [
                'version_label' => 'v1',
                'change_summary' => 'Oversized payload.',
                'system_prompt' => str_repeat('S', 12001),
                'user_prompt_template' => str_repeat('P', 20001),
                'variables_schema' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'notes' => 'Too large prompt body.',
                'preferred_model' => 'mock:team-lab-v1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['system_prompt', 'user_prompt_template']);
    }

    public function test_quick_test_rejects_oversized_runtime_payloads(): void
    {
        [$user, , $useCase] = $this->promptFixture('Payload Quick Test Team');

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
                'output_type' => 'json',
                'output_schema_json' => [
                    'data' => str_repeat('x', 13000),
                ],
                'input_text' => str_repeat('I', 12001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['output_schema_json', 'input_text']);
    }

    public function test_test_case_creation_rejects_oversized_json_payloads(): void
    {
        [$user, , $useCase] = $this->promptFixture('Payload Test Case Team');

        $this->actingAs($user)
            ->postJson(route('api.test-cases.store', $useCase), [
                'title' => 'Oversized metadata',
                'input_text' => 'Short input',
                'expected_output' => 'Expected text',
                'expected_json' => [],
                'variables_json' => [],
                'metadata_json' => [
                    'blob' => str_repeat('m', 13000),
                ],
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metadata_json']);
    }

    private function promptFixture(string $teamName): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => $teamName,
            'description' => 'Workspace for payload limit tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Payload fixture',
            'slug' => strtolower(str_replace(' ', '-', $teamName)),
            'description' => 'Payload limit fixture.',
            'business_goal' => 'Reject oversized payloads.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$user, $team, $useCase];
    }
}

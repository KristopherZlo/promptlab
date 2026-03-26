<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_editor_can_create_prompt_with_first_version_in_one_request(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Prompt Authoring Team',
            'description' => 'Workspace for prompt authoring tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Customer reply drafting',
            'slug' => 'customer-reply-drafting',
            'description' => 'Draft support replies.',
            'business_goal' => 'Speed up first response time.',
            'primary_input_label' => 'Customer email',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('api.prompts.store'), [
                'use_case_id' => $useCase->id,
                'name' => 'Support reply prompt',
                'description' => 'Drafts support replies.',
                'task_type' => 'rewrite',
                'status' => 'active',
                'preferred_model' => 'mock:team-lab-v1',
                'tags_json' => ['support'],
                'initial_version' => [
                    'version_label' => '',
                    'change_summary' => 'Initial prompt draft.',
                    'system_prompt' => 'You are a helpful support assistant.',
                    'user_prompt_template' => 'Draft a calm reply to {{input_text}}.',
                    'variables_schema' => [],
                    'output_type' => 'text',
                    'output_schema_json' => [],
                    'notes' => 'Start with a calm tone.',
                    'preferred_model' => 'mock:team-lab-v1',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support reply prompt')
            ->assertJsonPath('first_version_id', 1);

        $this->assertDatabaseHas('prompt_templates', [
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Support reply prompt',
        ]);

        $this->assertDatabaseHas('prompt_versions', [
            'team_id' => $team->id,
            'prompt_template_id' => 1,
            'version_label' => 'v1',
            'user_prompt_template' => 'Draft a calm reply to {{input_text}}.',
        ]);
    }

    public function test_team_editor_can_run_quick_test_from_prompt_draft(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Quick Test Team',
            'description' => 'Workspace for quick draft tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Meeting summary',
            'slug' => 'meeting-note-summarization',
            'description' => 'Summarize meeting notes.',
            'business_goal' => 'Produce faster summaries.',
            'primary_input_label' => 'Notes',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('api.prompts.quick-test'), [
                'use_case_id' => $useCase->id,
                'task_type' => 'summarization',
                'model_name' => 'mock:team-lab-v1',
                'temperature' => 0.2,
                'max_tokens' => 500,
                'system_prompt' => 'You summarize internal meetings.',
                'user_prompt_template' => 'Summarize this conversation:\n\n{{input_text}}',
                'variables_schema' => [],
                'variables' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'input_text' => 'User: We need a pilot review next Friday.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.format_valid', true)
            ->assertJsonPath('data.model_name', 'mock:team-lab-v1');

        $this->assertStringContainsString(
            'SYSTEM:',
            $response->json('data.compiled_prompt')
        );
        $this->assertStringContainsString(
            'Key decisions:',
            $response->json('data.output_text')
        );
    }

    public function test_prompt_version_update_keeps_existing_label_when_request_sends_blank_value(): void
    {
        [$user, $team, $useCase] = $this->promptFixture('Prompt Version Update Team');

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Update fixture prompt',
            'description' => 'Prompt version update regression fixture.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['regression'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v5',
            'change_summary' => 'Existing prompt revision.',
            'system_prompt' => 'Classify requests.',
            'user_prompt_template' => 'Classify {{input_text}}.',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'notes' => 'Original notes.',
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson(route('api.prompt-versions.update', $version), [
                'version_label' => '',
                'change_summary' => 'Updated without changing the version label.',
                'system_prompt' => 'Classify requests with a terse tone.',
                'user_prompt_template' => '{{customer_message}}',
                'variables_schema' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'notes' => 'Updated notes.',
                'preferred_model' => 'mock:team-lab-v1',
            ])
            ->assertOk()
            ->assertJsonPath('data.version_label', 'v5')
            ->assertJsonPath('data.user_prompt_template', '{{customer_message}}');

        $this->assertDatabaseHas('prompt_versions', [
            'id' => $version->id,
            'version_label' => 'v5',
            'user_prompt_template' => '{{customer_message}}',
        ]);
    }

    private function promptFixture(string $teamName): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => $teamName,
            'description' => 'Workspace for prompt authoring tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Prompt authoring fixture',
            'slug' => strtolower(str_replace(' ', '-', $teamName)),
            'description' => 'Prompt authoring fixture.',
            'business_goal' => 'Support prompt authoring tests.',
            'primary_input_label' => 'Input',
            'status' => 'active',
        ]);

        return [$user, $team, $useCase];
    }
}

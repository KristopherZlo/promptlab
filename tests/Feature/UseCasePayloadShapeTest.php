<?php

namespace Tests\Feature;

use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UseCasePayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_use_case_detail_returns_summary_shaped_prompt_payload_without_versions_tree(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Summary Payload Team',
            'description' => 'Workspace for payload shape checks.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Summary Payload',
            'slug' => 'summary-payload',
            'description' => 'Payload shape fixture.',
            'business_goal' => 'Keep use case payloads light.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Lean Prompt',
            'description' => 'Summary only.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PromptVersion::create([
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

        $this->actingAs($user)
            ->getJson(route('api.use-cases.show', $useCase))
            ->assertOk()
            ->assertJsonPath('useCase.prompt_templates.0.id', $template->id)
            ->assertJsonPath('useCase.prompt_templates.0.versions_count', 1)
            ->assertJsonMissingPath('useCase.prompt_templates.0.versions');
    }
}

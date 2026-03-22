<?php

namespace Tests\Feature;

use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TeamMembership;
use App\Models\UseCase;
use App\Models\User;
use App\Services\CurrentTeamResolver;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_admin_can_revoke_library_entry(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Library Team',
            'description' => 'Workspace for library entry tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Escalation routing',
            'slug' => 'escalation-routing',
            'description' => 'Route support tickets.',
            'business_goal' => 'Speed up escalations.',
            'primary_input_label' => 'Ticket text',
            'status' => 'active',
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Escalation router',
            'description' => 'Routes tickets into escalation buckets.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:triage-v1',
            'tags_json' => ['support'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v3',
            'change_summary' => 'Approved escalation thresholds.',
            'system_prompt' => 'Route tickets.',
            'user_prompt_template' => 'Classify {{input_text}}.',
            'variables_schema' => [],
            'output_type' => 'json',
            'output_schema_json' => [],
            'notes' => 'Stable for internal routing.',
            'preferred_model' => 'mock:triage-v1',
            'is_library_approved' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $entry = LibraryEntry::create([
            'team_id' => $team->id,
            'prompt_version_id' => $version->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'recommended_model' => 'mock:triage-v1',
            'best_for' => 'Support ticket routing',
            'usage_notes' => 'Use on inbound triage queue.',
        ]);

        $this->actingAs($user)
            ->delete(route('api.library-entries.destroy', $entry))
            ->assertOk()
            ->assertJson([
                'message' => 'Prompt removed from library.',
            ]);

        $this->assertDatabaseMissing('library_entries', [
            'id' => $entry->id,
        ]);
        $this->assertFalse($version->fresh()->is_library_approved);
    }

    public function test_prompt_editor_cannot_self_approve_prompt_versions(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $editor = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Prompt Approval Team',
            'description' => 'Workspace for approval boundary tests.',
        ]);

        TeamMembership::create([
            'team_id' => $team->id,
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);
        app(CurrentTeamResolver::class)->switchTo($editor, $team);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Approval boundary',
            'slug' => 'approval-boundary',
            'description' => 'Prompt approval fixture.',
            'business_goal' => 'Keep library approval restricted.',
            'primary_input_label' => 'Input',
            'status' => 'active',
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Approval fixture prompt',
            'description' => 'Prompt for approval security testing.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:triage-v1',
            'tags_json' => ['security'],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $response = $this->actingAs($editor)
            ->postJson(route('api.prompt-versions.store', $template), [
                'version_label' => 'v1',
                'change_summary' => 'Try to self-approve.',
                'system_prompt' => 'Classify clearly.',
                'user_prompt_template' => 'Classify {{input_text}}.',
                'variables_schema' => [],
                'output_type' => 'text',
                'output_schema_json' => [],
                'notes' => 'Editor authored draft.',
                'preferred_model' => 'mock:triage-v1',
                'is_library_approved' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_library_approved', false);

        $versionId = $response->json('data.id');

        $this->assertDatabaseHas('prompt_versions', [
            'id' => $versionId,
            'is_library_approved' => false,
        ]);
        $this->assertDatabaseMissing('library_entries', [
            'prompt_version_id' => $versionId,
        ]);
    }

    public function test_prompt_listing_requires_library_entry_for_approval_state(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Approval Listing Team',
            'description' => 'Workspace for approval display checks.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Approval listing',
            'slug' => 'approval-listing',
            'description' => 'Approval listing fixture.',
            'business_goal' => 'Show only real approvals.',
            'primary_input_label' => 'Input',
            'status' => 'active',
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Listing fixture prompt',
            'description' => 'Prompt for approval display testing.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:triage-v1',
            'tags_json' => ['security'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Tampered approval flag.',
            'system_prompt' => 'Classify clearly.',
            'user_prompt_template' => 'Classify {{input_text}}.',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'notes' => 'This should not look approved.',
            'preferred_model' => 'mock:triage-v1',
            'is_library_approved' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.prompts.show', $template))
            ->assertOk()
            ->assertJsonPath('promptTemplate.approval_state', 'pending')
            ->assertJsonPath('promptTemplate.approved_version_label', null)
            ->assertJsonPath('promptTemplate.versions.0.is_library_approved', false)
            ->assertJsonPath('promptTemplate.versions.0.library_entry', null);
    }
}

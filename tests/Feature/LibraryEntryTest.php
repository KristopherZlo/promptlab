<?php

namespace Tests\Feature;

use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
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
}

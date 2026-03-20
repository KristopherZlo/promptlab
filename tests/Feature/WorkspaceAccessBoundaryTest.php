<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_cannot_switch_into_workspace_without_membership(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $owner = User::factory()->create();

        app(TeamProvisioningService::class)->createTeam($admin, [
            'name' => 'Admin Team',
            'description' => 'Workspace owned by the admin user.',
        ]);
        $foreignTeam = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Foreign Team',
            'description' => 'Workspace the admin should not be able to enter.',
        ]);

        $this->actingAs($admin)
            ->postJson(route('api.teams.switch'), [
                'team_id' => $foreignTeam->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team_id');
    }

    public function test_workspace_setup_lists_only_member_workspaces_and_resets_invalid_current_workspace(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $owner = User::factory()->create();

        $memberTeam = app(TeamProvisioningService::class)->createTeam($admin, [
            'name' => 'Member Team',
            'description' => 'Workspace the admin belongs to.',
        ]);
        $foreignTeam = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Foreign Team',
            'description' => 'Workspace the admin should not be able to access.',
        ]);

        $admin->forceFill([
            'current_team_id' => $foreignTeam->id,
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get('/admin/workspaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Workspaces')
                ->where('currentWorkspace.id', $memberTeam->id)
                ->has('workspaces', 1)
                ->where('workspaces.0.id', $memberTeam->id)
                ->where('workspaces.0.name', 'Member Team')
            );

        $this->assertSame($memberTeam->id, $admin->fresh()->current_team_id);
    }
}

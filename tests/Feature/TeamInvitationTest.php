<?php

namespace Tests\Feature;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_admin_can_create_workspace_invitation(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Invite Team',
            'description' => 'Workspace for invitation tests.',
        ]);

        $this->actingAs($user)
            ->post(route('api.team-invitations.store'), [
                'email' => 'invitee@example.com',
                'role' => 'reviewer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.role', 'reviewer')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'email' => 'invitee@example.com',
            'role' => 'reviewer',
            'status' => 'pending',
        ]);
        $this->assertTrue(TeamInvitation::query()->where('team_id', $team->id)->where('email', 'invitee@example.com')->exists());
    }
}

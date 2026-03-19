<?php

namespace Tests\Feature;

use App\Models\TeamInvitation;
use App\Models\TeamMembership;
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

    public function test_invited_user_can_accept_workspace_invitation(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Acceptance Team',
            'description' => 'Workspace for accepting invitations.',
        ]);

        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'email' => 'invitee@example.com',
            'role' => 'editor',
            'token' => 'accept-token',
            'status' => 'pending',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($invitee)
            ->post(route('team-invitations.accept', $invitation->token))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('team_memberships', [
            'team_id' => $team->id,
            'user_id' => $invitee->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull(TeamMembership::query()->where('team_id', $team->id)->where('user_id', $invitee->id)->first());
        $this->assertSame($team->id, $invitee->fresh()->current_team_id);
    }
}

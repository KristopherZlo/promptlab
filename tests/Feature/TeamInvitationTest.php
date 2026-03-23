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

        $response = $this->actingAs($user)
            ->postJson(route('api.team-invitations.store'), [
                'email' => 'invitee@example.com',
                'role' => 'reviewer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.role', 'reviewer')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.token');

        $invitation = TeamInvitation::query()->where('team_id', $team->id)->where('email', 'invitee@example.com')->firstOrFail();
        $rawToken = basename((string) parse_url($response->json('data.invite_url'), PHP_URL_PATH));

        $this->assertSame(hash('sha256', $rawToken), $invitation->token);
        $this->assertNotSame($rawToken, $invitation->token);
        $this->assertSame($rawToken, $invitation->token_ciphertext);
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
            'token' => hash('sha256', 'accept-token'),
            'token_ciphertext' => 'accept-token',
            'status' => 'pending',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($invitee)
            ->post(route('team-invitations.accept', 'accept-token'))
            ->assertRedirect(route('getting-started', absolute: false));

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

    public function test_team_admin_can_revoke_pending_invitation(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Revocation Team',
            'description' => 'Workspace for revoking invitations.',
        ]);

        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'email' => 'invitee@example.com',
            'role' => 'reviewer',
            'token' => hash('sha256', 'revoke-token'),
            'token_ciphertext' => 'revoke-token',
            'status' => 'pending',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)
            ->delete(route('api.team-invitations.destroy', $invitation->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => 'revoked',
        ]);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }
}

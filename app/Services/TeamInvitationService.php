<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly CurrentTeamResolver $resolver,
    ) {
    }

    public function createInvitation(Team $team, User $actor, string $email, string $role): TeamInvitation
    {
        $normalizedEmail = Str::lower(trim($email));
        $plainToken = Str::random(64);

        if ($team->memberships()->whereHas('user', fn ($query) => $query->where('email', $normalizedEmail))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the current workspace.',
            ]);
        }

        $invitation = TeamInvitation::updateOrCreate(
            [
                'team_id' => $team->id,
                'email' => $normalizedEmail,
                'status' => 'pending',
            ],
            [
                'role' => $role,
                'token' => hash('sha256', $plainToken),
                'token_ciphertext' => $plainToken,
                'invited_by' => $actor->id,
                'accepted_at' => null,
                'revoked_at' => null,
                'expires_at' => now()->addDays(7),
            ],
        );

        $this->activity->record('team.invitation_created', $invitation, [
            'member_email' => $invitation->email,
            'role' => $invitation->role,
            'team_name' => $team->name,
            'expires_at' => optional($invitation->expires_at)->toIso8601String(),
        ], $actor, $team->id);

        return $this->attachPublicToken($invitation->fresh(['inviter']), $plainToken);
    }

    public function findByToken(string $token): ?TeamInvitation
    {
        if (blank($token)) {
            return null;
        }

        $invitation = TeamInvitation::query()
            ->with(['team', 'inviter'])
            ->where('token', hash('sha256', $token))
            ->first();

        return $invitation ? $this->attachPublicToken($invitation, $token) : null;
    }

    public function statusFor(TeamInvitation $invitation): string
    {
        if ($invitation->status !== 'pending') {
            return $invitation->status;
        }

        if ($invitation->expires_at?->isPast()) {
            return 'expired';
        }

        return 'pending';
    }

    public function acceptInvitation(TeamInvitation $invitation, User $user): TeamMembership
    {
        $status = $this->statusFor($invitation);

        if ($status !== 'pending') {
            throw ValidationException::withMessages([
                'invitation' => "This invitation is {$status} and can no longer be accepted.",
            ]);
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => 'Sign in with the email address that received this invitation.',
            ]);
        }

        $membership = TeamMembership::firstOrCreate(
            [
                'team_id' => $invitation->team_id,
                'user_id' => $user->id,
            ],
            [
                'role' => $invitation->role,
            ],
        );

        $invitation->forceFill([
            'status' => 'accepted',
            'accepted_at' => now(),
        ])->save();

        $this->resolver->switchTo($user, $invitation->team);
        $this->activity->record('team.invitation_accepted', $membership, [
            'member_email' => $user->email,
            'role' => $membership->role,
            'team_name' => $invitation->team?->name,
        ], $user, $invitation->team_id);

        return $membership->fresh(['user']);
    }

    public function revokeInvitation(Team $team, User $actor, TeamInvitation $invitation): TeamInvitation
    {
        $status = $this->statusFor($invitation);

        if ($status === 'accepted') {
            throw ValidationException::withMessages([
                'invitation' => 'Accepted invitations cannot be revoked.',
            ]);
        }

        if ($status !== 'revoked') {
            $invitation->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
            ])->save();

            $this->activity->record('team.invitation_revoked', $invitation, [
                'member_email' => $invitation->email,
                'role' => $invitation->role,
                'team_name' => $team->name,
            ], $actor, $team->id);
        }

        return $this->attachPublicToken(
            $invitation->fresh(['team', 'inviter']),
            $invitation->token_ciphertext,
        );
    }

    private function attachPublicToken(TeamInvitation $invitation, ?string $plainToken): TeamInvitation
    {
        $invitation->public_token = $plainToken;

        return $invitation;
    }
}

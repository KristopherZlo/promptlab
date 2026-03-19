<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    public function __construct(
        private readonly ActivityLogService $activity,
    ) {
    }

    public function createInvitation(Team $team, User $actor, string $email, string $role): TeamInvitation
    {
        $normalizedEmail = Str::lower(trim($email));

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
                'token' => Str::random(64),
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

        return $invitation->fresh(['inviter']);
    }
}

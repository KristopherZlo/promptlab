<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamProvisioningService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly CurrentTeamResolver $resolver,
    ) {
    }

    public function ensurePersonalWorkspace(User $user, ?string $workspaceName = null): Team
    {
        if ($this->resolver->currentTeam($user)) {
            return $this->resolver->currentTeam($user);
        }

        $team = $this->createTeam($user, [
            'name' => $workspaceName ?: "{$user->display_name} Workspace",
            'description' => 'Personal workspace created during account setup.',
        ]);

        return $team;
    }

    public function createTeam(User $owner, array $attributes): Team
    {
        return DB::transaction(function () use ($owner, $attributes): Team {
            $team = Team::create([
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['name']),
                'description' => $attributes['description'] ?? null,
                'created_by' => $owner->id,
            ]);

            TeamMembership::create([
                'team_id' => $team->id,
                'user_id' => $owner->id,
                'role' => TeamPermissionService::ROLE_OWNER,
            ]);

            $this->resolver->switchTo($owner, $team);
            $this->activity->record('team.created', $team, [
                'name' => $team->name,
                'description' => $team->description,
            ], $owner, $team->id);

            return $team->fresh(['memberships.user']);
        });
    }

    public function addMember(Team $team, User $actor, string $email, string $role): TeamMembership
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Only existing Evala users can be added to a team.',
            ]);
        }

        $membership = TeamMembership::updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
            ]
        );

        if (! $user->current_team_id) {
            $this->resolver->switchTo($user, $team);
        }

        $this->activity->record('team.member_added', $membership, [
            'member_name' => $user->display_name,
            'member_email' => $user->email,
            'role' => $role,
            'team_name' => $team->name,
        ], $actor, $team->id);

        return $membership->fresh(['user']);
    }

    public function updateMembershipRole(Team $team, User $actor, TeamMembership $membership, string $role): TeamMembership
    {
        $this->assertOwnerWillRemain($team, $membership, $role);

        $membership->update(['role' => $role]);

        $this->activity->record('team.member_role_updated', $membership, [
            'member_name' => $membership->user?->display_name,
            'member_email' => $membership->user?->email,
            'role' => $role,
            'team_name' => $team->name,
        ], $actor, $team->id);

        return $membership->fresh(['user']);
    }

    public function removeMembership(Team $team, User $actor, TeamMembership $membership): void
    {
        $this->assertOwnerCanBeRemoved($team, $membership);

        $member = $membership->user;
        $membership->delete();

        if ($member && $member->current_team_id === $team->id) {
            $nextTeamId = $member->memberships()->orderBy('id')->value('team_id');
            $member->forceFill(['current_team_id' => $nextTeamId])->saveQuietly();
        }

        $this->activity->record('team.member_removed', $team, [
            'member_name' => $member?->display_name,
            'member_email' => $member?->email,
            'team_name' => $team->name,
        ], $actor, $team->id);
    }

    private function assertOwnerWillRemain(Team $team, TeamMembership $membership, string $nextRole): void
    {
        if (
            $membership->role === TeamPermissionService::ROLE_OWNER
            && $nextRole !== TeamPermissionService::ROLE_OWNER
            && $team->memberships()->where('role', TeamPermissionService::ROLE_OWNER)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => 'Each team must keep at least one owner.',
            ]);
        }
    }

    private function assertOwnerCanBeRemoved(Team $team, TeamMembership $membership): void
    {
        if (
            $membership->role === TeamPermissionService::ROLE_OWNER
            && $team->memberships()->where('role', TeamPermissionService::ROLE_OWNER)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'membership' => 'The last owner cannot be removed from the team.',
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $counter = 2;

        while (Team::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

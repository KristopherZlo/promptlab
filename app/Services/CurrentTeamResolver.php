<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CurrentTeamResolver
{
    public function currentTeamId(?User $user = null): ?int
    {
        return $this->currentTeam($user)?->id;
    }

    public function currentTeam(?User $user = null): ?Team
    {
        $user ??= Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->current_team_id) {
            if (
                ! $user->isAdmin()
                && ! $user->memberships()->where('team_id', $user->current_team_id)->exists()
            ) {
                $user->forceFill(['current_team_id' => null])->saveQuietly();

                return $this->ensureFor($user);
            }

            return $user->relationLoaded('currentTeam')
                ? $user->currentTeam
                : Team::query()->find($user->current_team_id);
        }

        return $this->ensureFor($user);
    }

    public function ensureFor(?User $user): ?Team
    {
        if (! $user) {
            return null;
        }

        if ($user->current_team_id) {
            return $this->currentTeam($user);
        }

        $membership = $user->memberships()->with('team')->orderBy('id')->first();

        if (! $membership) {
            return null;
        }

        $user->forceFill(['current_team_id' => $membership->team_id])->saveQuietly();
        $user->setRelation('currentTeam', $membership->team);

        return $membership->team;
    }

    public function switchTo(User $user, Team $team): void
    {
        $user->forceFill(['current_team_id' => $team->id])->saveQuietly();
        $user->setRelation('currentTeam', $team);
    }

    public function teamsFor(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return $user->teams()
            ->select('teams.id', 'teams.name', 'teams.slug', 'teams.description')
            ->orderBy('teams.name')
            ->get();
    }
}

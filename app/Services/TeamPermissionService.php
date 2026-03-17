<?php

namespace App\Services;

use App\Models\TeamMembership;
use App\Models\User;

class TeamPermissionService
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_VIEWER = 'viewer';

    private const ABILITY_MATRIX = [
        self::ROLE_OWNER => [
            'view_workspace',
            'manage_team',
            'manage_members',
            'manage_connections',
            'view_audit',
            'manage_use_cases',
            'manage_prompts',
            'manage_test_cases',
            'run_experiments',
            'evaluate_runs',
            'manage_library',
        ],
        self::ROLE_ADMIN => [
            'view_workspace',
            'manage_team',
            'manage_members',
            'manage_connections',
            'view_audit',
            'manage_use_cases',
            'manage_prompts',
            'manage_test_cases',
            'run_experiments',
            'evaluate_runs',
            'manage_library',
        ],
        self::ROLE_EDITOR => [
            'view_workspace',
            'manage_use_cases',
            'manage_prompts',
            'manage_test_cases',
            'run_experiments',
            'evaluate_runs',
        ],
        self::ROLE_REVIEWER => [
            'view_workspace',
            'run_experiments',
            'evaluate_runs',
        ],
        self::ROLE_VIEWER => [
            'view_workspace',
        ],
    ];

    public function validRoles(): array
    {
        return array_keys(self::ABILITY_MATRIX);
    }

    public function can(?User $user, string $ability, ?int $teamId = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $role = $this->roleFor($user, $teamId);

        if (! $role) {
            return false;
        }

        return in_array($ability, self::ABILITY_MATRIX[$role] ?? [], true);
    }

    public function abilitiesFor(?User $user, ?int $teamId = null): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isAdmin()) {
            return self::ABILITY_MATRIX[self::ROLE_OWNER];
        }

        $role = $this->roleFor($user, $teamId);

        return $role ? (self::ABILITY_MATRIX[$role] ?? []) : [];
    }

    public function roleFor(User $user, ?int $teamId = null): ?string
    {
        $teamId ??= app(CurrentTeamResolver::class)->currentTeamId($user);

        if (! $teamId) {
            return null;
        }

        $loaded = $user->relationLoaded('memberships')
            ? $user->memberships->firstWhere('team_id', $teamId)
            : null;

        if ($loaded instanceof TeamMembership) {
            return $loaded->role;
        }

        return $user->memberships()
            ->where('team_id', $teamId)
            ->value('role');
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\CurrentTeamResolver;
use App\Services\TeamPermissionService;
use App\Services\WorkspaceJourneyService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentTeam = app(CurrentTeamResolver::class)->ensureFor($user);
        $teams = app(CurrentTeamResolver::class)->teamsFor($user);
        $teamRole = $user ? app(TeamPermissionService::class)->roleFor($user, $currentTeam?->id) : null;
        $abilities = app(TeamPermissionService::class)->abilitiesFor($user, $currentTeam?->id);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'platform_role' => $user->role,
                    'current_team_id' => $user->current_team_id,
                    'effective_abilities' => $abilities,
                ] : null,
                'current_team' => $currentTeam ? [
                    'id' => $currentTeam->id,
                    'name' => $currentTeam->name,
                    'slug' => $currentTeam->slug,
                    'description' => $currentTeam->description,
                    'role' => $teamRole,
                    'team_role' => $teamRole,
                ] : null,
                'teams' => $teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'description' => $team->description,
                    'role' => $team->pivot?->role,
                    'team_role' => $team->pivot?->role,
                ])->values()->all(),
                'abilities' => $abilities,
            ],
            'navigation' => [
                'home_url' => $user ? app(WorkspaceJourneyService::class)->landingUrl() : route('login'),
                'sections' => $this->navigationSections($abilities),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }

    private function navigationSections(array $abilities): array
    {
        $canSeeAdministration = collect($abilities)
            ->intersect(['manage_members', 'manage_team', 'manage_connections', 'view_audit'])
            ->isNotEmpty();

        return array_values(array_filter([
            [
                'id' => 'workspace',
                'label' => 'Workspace',
                'items' => array_values(array_filter([
                    $this->navigationItem('tasks', 'Tasks', 'use-cases.index', ['use-cases.*']),
                    $this->navigationItem('prompts', 'Prompts', 'prompt-templates.index', ['prompt-templates.*', 'prompt-versions.*']),
                    $this->navigationItem('experiments', 'Experiments', 'playground', ['playground', 'experiments.show']),
                    $this->navigationItem('library', 'Library', 'library.index', ['library.*']),
                    $this->navigationItem('dashboard', 'Dashboard', 'dashboard', ['dashboard']),
                ])),
            ],
            $canSeeAdministration ? [
                'id' => 'administration',
                'label' => 'Administration',
                'items' => array_values(array_filter([
                    $this->navigationItem('users-access', 'Users & Access', 'admin.users-access', ['admin.users-access'], in_array('manage_members', $abilities, true)),
                    $this->navigationItem('ai-connections', 'AI Connections', 'admin.ai-connections', ['admin.ai-connections'], in_array('manage_connections', $abilities, true)),
                    $this->navigationItem('audit-log', 'Audit Log', 'admin.audit-log', ['admin.audit-log'], in_array('view_audit', $abilities, true)),
                ])),
            ] : null,
            [
                'id' => 'account',
                'label' => 'Account',
                'items' => array_values(array_filter([
                    $this->navigationItem('profile', 'Profile', 'profile.edit', ['profile.*']),
                ])),
            ],
        ]));
    }

    private function navigationItem(
        string $id,
        string $label,
        string $route,
        array $current,
        bool $visible = true,
    ): ?array {
        if (! $visible) {
            return null;
        }

        return [
            'id' => $id,
            'label' => $label,
            'route' => $route,
            'current' => $current,
        ];
    }
}

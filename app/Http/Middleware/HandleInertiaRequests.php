<?php

namespace App\Http\Middleware;

use App\Services\CurrentTeamResolver;
use App\Services\TeamPermissionService;
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
        $currentTeam = app(CurrentTeamResolver::class)->ensureFor($request->user());
        $teams = app(CurrentTeamResolver::class)->teamsFor($request->user());
        $abilities = app(TeamPermissionService::class)->abilitiesFor($request->user(), $currentTeam?->id);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->display_name,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'display_name' => $request->user()->display_name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'current_team_id' => $request->user()->current_team_id,
                ] : null,
                'current_team' => $currentTeam ? [
                    'id' => $currentTeam->id,
                    'name' => $currentTeam->name,
                    'slug' => $currentTeam->slug,
                    'description' => $currentTeam->description,
                    'role' => app(TeamPermissionService::class)->roleFor($request->user(), $currentTeam->id),
                ] : null,
                'teams' => $teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'description' => $team->description,
                    'role' => $team->pivot?->role,
                ])->values()->all(),
                'abilities' => $abilities,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}

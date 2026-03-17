<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\CurrentTeamResolver;
use App\Services\TeamPermissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    protected function authorizeTeamAbility(Request $request, string $ability): void
    {
        abort_unless(
            app(TeamPermissionService::class)->can($request->user(), $ability),
            403,
            'You do not have permission to perform this action in the current team.'
        );
    }

    protected function currentTeam(Request $request): Team
    {
        $team = app(CurrentTeamResolver::class)->currentTeam($request->user());

        if (! $team) {
            throw new HttpException(403, 'Select or create a team workspace first.');
        }

        return $team;
    }

    protected function isApiRequest(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }
}

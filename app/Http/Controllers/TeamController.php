<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamStoreRequest;
use App\Http\Requests\TeamSwitchRequest;
use App\Models\Team;
use App\Services\ActivityLogService;
use App\Services\CurrentTeamResolver;
use App\Services\TeamProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function store(TeamStoreRequest $request, TeamProvisioningService $provisioning): JsonResponse
    {
        $team = $provisioning->createTeam($request->user(), $request->validated());

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
            ],
            'redirect_url' => route('admin.workspaces'),
        ], 201);
    }

    public function switch(TeamSwitchRequest $request, CurrentTeamResolver $resolver, ActivityLogService $activity): JsonResponse
    {
        $team = Team::query()->findOrFail($request->integer('team_id'));
        $user = $request->user();

        if (! $user->memberships()->where('team_id', $team->id)->exists()) {
            throw ValidationException::withMessages([
                'team_id' => 'You are not a member of the selected team.',
            ]);
        }

        $resolver->switchTo($user, $team);
        $activity->record('team.switched', $team, ['name' => $team->name], $user, $team->id);

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamMembershipStoreRequest;
use App\Http\Requests\TeamMembershipUpdateRequest;
use App\Models\TeamMembership;
use App\Services\TeamProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMembershipController extends Controller
{
    public function store(TeamMembershipStoreRequest $request, TeamProvisioningService $provisioning): JsonResponse
    {
        $membership = $provisioning->addMember(
            $this->currentTeam($request),
            $request->user(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => $membership->load('user')], 201);
    }

    public function update(TeamMembershipUpdateRequest $request, TeamMembership $teamMembership, TeamProvisioningService $provisioning): JsonResponse
    {
        abort_unless($teamMembership->team_id === $this->currentTeam($request)->id, 404);

        $membership = $provisioning->updateMembershipRole(
            $this->currentTeam($request),
            $request->user(),
            $teamMembership->load('user'),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => $membership]);
    }

    public function destroy(Request $request, TeamMembership $teamMembership, TeamProvisioningService $provisioning): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'manage_members');
        abort_unless($teamMembership->team_id === $this->currentTeam($request)->id, 404);

        $provisioning->removeMembership(
            $this->currentTeam($request),
            $request->user(),
            $teamMembership->load('user'),
        );

        return response()->json(status: 204);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamInvitationStoreRequest;
use App\Http\Resources\TeamInvitationResource;
use App\Services\TeamInvitationService;
use Illuminate\Http\JsonResponse;

class TeamInvitationController extends Controller
{
    public function store(TeamInvitationStoreRequest $request, TeamInvitationService $invitations): JsonResponse
    {
        $invitation = $invitations->createInvitation(
            $this->currentTeam($request),
            $request->user(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => new TeamInvitationResource($invitation)], 201);
    }
}

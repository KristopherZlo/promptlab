<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamInvitationStoreRequest;
use App\Http\Resources\TeamInvitationResource;
use App\Services\TeamInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    public function store(TeamInvitationStoreRequest $request, TeamInvitationService $invitations): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'manage_members');

        $invitation = $invitations->createInvitation(
            $this->currentTeam($request),
            $request->user(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => new TeamInvitationResource($invitation)], 201);
    }

    public function show(Request $request, string $token, TeamInvitationService $invitations): Response
    {
        $invitation = $invitations->findByToken($token);
        $user = $request->user();
        $emailMatches = $invitation && $user
            ? strcasecmp($user->email, $invitation->email) === 0
            : false;

        return Inertia::render('TeamInvitations/Show', [
            'invitation' => $invitation ? (new TeamInvitationResource($invitation))->resolve() : null,
            'canAccept' => (bool) ($user && $invitation && $emailMatches && $invitations->statusFor($invitation) === 'pending'),
            'emailMatches' => $emailMatches,
        ]);
    }

    public function accept(Request $request, string $token, TeamInvitationService $invitations): RedirectResponse
    {
        $invitation = $invitations->findByToken($token);
        abort_unless($invitation, 404);

        $invitations->acceptInvitation($invitation, $request->user());

        return to_route('dashboard')->with('success', 'Workspace invitation accepted.');
    }

    public function destroy(Request $request, \App\Models\TeamInvitation $teamInvitation, TeamInvitationService $invitations): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'manage_members');
        abort_unless($teamInvitation->team_id === $this->currentTeam($request)->id, 404);

        $invitation = $invitations->revokeInvitation(
            $this->currentTeam($request),
            $request->user(),
            $teamInvitation->loadMissing(['team', 'inviter']),
        );

        return response()->json(['data' => new TeamInvitationResource($invitation)]);
    }
}

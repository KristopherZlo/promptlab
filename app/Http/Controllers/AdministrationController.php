<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Http\Resources\TeamInvitationResource;
use App\Services\TeamPermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function usersAccess(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'manage_members');

        $team = $this->currentTeam($request)->loadMissing([
            'creator',
            'memberships.user',
            'invitations.inviter',
        ]);

        return Inertia::render('Admin/UsersAccess', [
            'team' => $this->teamPayload($team),
            'memberships' => $this->membershipsPayload($team),
            'invitations' => TeamInvitationResource::collection($team->invitations)->resolve(),
            'roleOptions' => app(TeamPermissionService::class)->validRoles(),
        ]);
    }

    public function workspaces(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'manage_team');

        $currentTeam = $this->currentTeam($request)->loadMissing('creator');

        $workspaces = Team::query()
            ->with('creator')
            ->withCount('memberships')
            ->whereIn('id', $request->user()->memberships()->select('team_id'))
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Workspaces', [
            'currentWorkspace' => $this->workspacePayload($currentTeam, true),
            'workspaces' => $workspaces->map(fn (Team $team) => $this->workspacePayload($team))->values()->all(),
        ]);
    }

    public function aiConnections(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'manage_connections');

        $team = $this->currentTeam($request)->loadMissing([
            'creator',
            'llmConnections',
        ]);

        return Inertia::render('Admin/AiConnections', [
            'team' => $this->teamPayload($team),
            'connections' => $team->llmConnections->map(fn ($connection) => [
                'id' => $connection->id,
                'name' => $connection->name,
                'driver' => $connection->driver,
                'base_url' => $connection->base_url,
                'models_json' => $connection->models_json ?? [],
                'has_api_key' => filled($connection->api_key),
                'is_active' => $connection->is_active,
                'is_default' => $connection->is_default,
                'updated_at' => optional($connection->updated_at)->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function auditLog(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'view_audit');

        $team = $this->currentTeam($request)->loadMissing('creator');
        $entries = ActivityLog::query()
            ->with('actor')
            ->where('team_id', $team->id)
            ->latest()
            ->take(80)
            ->get();

        return Inertia::render('Admin/AuditLog', [
            'team' => $this->teamPayload($team),
            'entries' => $entries->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_label' => $log->subject_label,
                'details_json' => $log->details_json ?? [],
                'actor' => $log->actor?->display_name,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    private function teamPayload(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'description' => $team->description,
            'creator' => $team->creator?->display_name,
            'members_count' => $team->memberships()->count(),
            'connections_count' => $team->llmConnections()->count(),
            'audit_count' => $team->activityLogs()->count(),
        ];
    }

    private function membershipsPayload(Team $team): array
    {
        $permissions = app(TeamPermissionService::class);

        return $team->memberships->map(fn ($membership) => [
            'id' => $membership->id,
            'team_role' => $membership->role,
            'abilities' => $permissions->abilitiesFor($membership->user, $team->id),
            'user' => [
                'id' => $membership->user?->id,
                'name' => $membership->user?->display_name,
                'display_name' => $membership->user?->display_name,
                'email' => $membership->user?->email,
                'platform_role' => $membership->user?->role,
            ],
        ])->values()->all();
    }

    private function workspacePayload(Team $team, bool $isCurrent = false): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'description' => $team->description,
            'creator' => $team->creator?->display_name,
            'members_count' => $team->memberships_count ?? $team->memberships()->count(),
            'is_current' => $isCurrent,
        ];
    }
}

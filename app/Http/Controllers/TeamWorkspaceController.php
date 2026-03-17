<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\TeamPermissionService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class TeamWorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $team = $this->currentTeam($request)->load([
            'memberships.user',
            'llmConnections',
        ]);

        $canViewAudit = $request->user()->canInTeam('view_audit', $team->id);
        $activityLogs = $canViewAudit
            ? ActivityLog::query()->with('actor')->where('team_id', $team->id)->latest()->take(40)->get()
            : collect();

        return Inertia::render('TeamWorkspace/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'description' => $team->description,
                'memberships' => $team->memberships->map(fn ($membership) => [
                    'id' => $membership->id,
                    'role' => $membership->role,
                    'user' => [
                        'id' => $membership->user?->id,
                        'name' => $membership->user?->display_name,
                        'first_name' => $membership->user?->first_name,
                        'last_name' => $membership->user?->last_name,
                        'display_name' => $membership->user?->display_name,
                        'email' => $membership->user?->email,
                        'platform_role' => $membership->user?->role,
                    ],
                ])->values()->all(),
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
                'activity_logs' => $activityLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'subject_label' => $log->subject_label,
                    'details_json' => $log->details_json ?? [],
                    'actor' => $log->actor?->display_name,
                    'created_at' => optional($log->created_at)->toIso8601String(),
                ])->values()->all(),
            ],
            'roleOptions' => app(TeamPermissionService::class)->validRoles(),
        ]);
    }
}

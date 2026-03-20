<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamWorkspaceController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        if ($request->user()->canInTeam('manage_team')) {
            return to_route('admin.workspaces');
        }

        if ($request->user()->canInTeam('manage_members')) {
            return to_route('admin.users-access');
        }

        if ($request->user()->canInTeam('manage_connections')) {
            return to_route('admin.ai-connections');
        }

        if ($request->user()->canInTeam('view_audit')) {
            return to_route('admin.audit-log');
        }

        return to_route('dashboard');
    }
}

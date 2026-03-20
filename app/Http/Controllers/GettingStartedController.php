<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\WorkspaceJourneyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GettingStartedController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics, WorkspaceJourneyService $journey): Response
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return Inertia::render('GettingStarted', [
            'overview' => $analytics->overview(),
            'journey' => $journey->snapshot(),
        ]);
    }
}

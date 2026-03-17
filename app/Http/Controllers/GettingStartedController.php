<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GettingStartedController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return Inertia::render('GettingStarted', [
            'overview' => $analytics->overview(),
        ]);
    }
}

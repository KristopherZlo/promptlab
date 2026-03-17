<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\LLMProviderManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics, LLMProviderManager $providers): Response
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return Inertia::render('Dashboard', [
            'overview' => $analytics->overview(),
            'models' => $providers->availableModels(),
        ]);
    }
}

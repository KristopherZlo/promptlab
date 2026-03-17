<?php

namespace App\Http\Controllers;

use App\Models\UseCase;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function overview(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return response()->json($analytics->overview());
    }

    public function useCase(Request $request, UseCase $useCase, AnalyticsService $analytics): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        return response()->json($analytics->useCaseDetail($useCase));
    }
}

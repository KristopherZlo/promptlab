<?php

namespace App\Http\Controllers;

use App\Models\Experiment;
use App\Models\UseCase;
use App\Services\LLMProviderManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaygroundController extends Controller
{
    public function index(Request $request, LLMProviderManager $providers): Response
    {
        $this->authorizeTeamAbility($request, 'run_experiments');

        return Inertia::render('Playground/Index', [
            'useCases' => UseCase::with([
                'promptTemplates.versions',
                'testCases',
            ])->orderBy('name')->get(),
            'models' => $providers->availableModels(),
            'recentExperiments' => Experiment::with('useCase')
                ->latest()
                ->take(8)
                ->get(['id', 'use_case_id', 'mode', 'status', 'model_name', 'created_at']),
        ]);
    }
}

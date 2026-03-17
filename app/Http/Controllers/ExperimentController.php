<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExperimentRequest;
use App\Http\Resources\ExperimentResource;
use App\Models\Experiment;
use App\Services\ActivityLogService;
use App\Services\ExperimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExperimentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $experiments = Experiment::with('useCase')
            ->latest()
            ->take(20)
            ->get();

        return response()->json(['data' => ExperimentResource::collection($experiments)]);
    }

    public function show(Request $request, Experiment $experiment, ExperimentService $experiments): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $experiment = $experiments->loadExperiment($experiment);

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => new ExperimentResource($experiment)]);
        }

        return Inertia::render('Experiments/Show', [
            'experiment' => (new ExperimentResource($experiment))->resolve(),
        ]);
    }

    public function store(ExperimentRequest $request, ExperimentService $experiments, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $experiment = $validated['mode'] === 'batch'
            ? $experiments->queueBatch($request->user(), $validated)
            : $experiments->runInteractive($request->user(), $validated);
        $activity->record('experiment.created', $experiment, [
            'mode' => $experiment->mode,
            'model_name' => $experiment->model_name,
            'total_runs' => $experiment->total_runs,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new ExperimentResource($experiment),
                'redirect_url' => route('experiments.show', $experiment),
            ], 201);
        }

        return to_route('experiments.show', $experiment)->with('success', 'Experiment started.');
    }
}

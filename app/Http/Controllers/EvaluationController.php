<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationRequest;
use App\Models\Evaluation;
use App\Models\ExperimentRun;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;

class EvaluationController extends Controller
{
    public function store(EvaluationRequest $request, ActivityLogService $activity): JsonResponse
    {
        $validated = $request->validated();
        $run = ExperimentRun::query()->findOrFail($validated['experiment_run_id']);

        $evaluation = Evaluation::updateOrCreate(
            [
                'experiment_run_id' => $validated['experiment_run_id'],
                'evaluator_id' => $request->user()->id,
            ],
            $validated + [
                'team_id' => $run->team_id,
                'evaluator_id' => $request->user()->id,
            ]
        );
        $activity->record('evaluation.saved', $evaluation, [
            'experiment_run_id' => $run->id,
            'status' => $run->status,
        ], $request->user());

        return response()->json([
            'data' => $evaluation->fresh(),
        ], 201);
    }
}

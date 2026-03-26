<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationRequest;
use App\Models\Evaluation;
use App\Models\ExperimentRun;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    public function store(EvaluationRequest $request, ActivityLogService $activity): JsonResponse
    {
        $validated = $request->validated();
        $run = ExperimentRun::query()->findOrFail($validated['experiment_run_id']);

        if (! $run->isReviewable()) {
            throw ValidationException::withMessages([
                'experiment_run_id' => 'This run is not ready for manual evaluation yet. Wait until it finishes and produces a reviewable output.',
            ]);
        }

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

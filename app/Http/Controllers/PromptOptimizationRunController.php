<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptOptimizationRunRequest;
use App\Http\Resources\PromptOptimizationRunResource;
use App\Models\PromptTemplate;
use App\Services\PromptOptimizationService;
use Illuminate\Http\JsonResponse;

class PromptOptimizationRunController extends Controller
{
    public function store(
        PromptOptimizationRunRequest $request,
        PromptTemplate $promptTemplate,
        PromptOptimizationService $optimizations,
    ): JsonResponse {
        $run = $optimizations->startRun($request->user(), $promptTemplate, $request->validated());

        return response()->json([
            'data' => (new PromptOptimizationRunResource($run))->resolve(),
        ], 201);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\LlmConnectionRequest;
use App\Models\LlmConnection;
use App\Services\LlmConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmConnectionController extends Controller
{
    public function store(LlmConnectionRequest $request, LlmConnectionService $connections): JsonResponse
    {
        $connection = $connections->save(
            $this->currentTeam($request),
            $request->user(),
            $request->validated(),
        );

        return response()->json(['data' => $connection], 201);
    }

    public function update(LlmConnectionRequest $request, LlmConnection $llmConnection, LlmConnectionService $connections): JsonResponse
    {
        $connection = $connections->save(
            $this->currentTeam($request),
            $request->user(),
            $request->validated(),
            $llmConnection,
        );

        return response()->json(['data' => $connection]);
    }

    public function destroy(Request $request, LlmConnection $llmConnection, LlmConnectionService $connections): JsonResponse
    {
        $this->authorizeTeamAbility($request, 'manage_connections');
        $connections->delete($this->currentTeam($request), $request->user(), $llmConnection);

        return response()->json(status: 204);
    }
}

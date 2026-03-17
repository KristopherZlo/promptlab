<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestCaseRequest;
use App\Models\TestCase;
use App\Models\UseCase;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestCaseController extends Controller
{
    public function store(TestCaseRequest $request, UseCase $useCase, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $testCase = TestCase::create($request->validated() + [
            'team_id' => $useCase->team_id,
            'use_case_id' => $useCase->id,
        ]);
        $activity->record('test_case.created', $testCase, [
            'title' => $testCase->title,
            'use_case_name' => $useCase->name,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => $testCase], 201);
        }

        return to_route('use-cases.show', $useCase)->with('success', 'Test case created.');
    }

    public function update(TestCaseRequest $request, TestCase $testCase, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $testCase->update($request->validated());
        $activity->record('test_case.updated', $testCase, [
            'title' => $testCase->title,
            'use_case_name' => $testCase->useCase?->name,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => $testCase]);
        }

        return to_route('use-cases.show', $testCase->useCase)->with('success', 'Test case updated.');
    }

    public function destroy(Request $request, TestCase $testCase, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'manage_test_cases');

        $useCase = $testCase->useCase;
        $activity->record('test_case.deleted', $testCase, [
            'title' => $testCase->title,
            'use_case_name' => $useCase?->name,
        ], $request->user());
        $testCase->delete();

        if ($this->isApiRequest($request)) {
            return response()->json(status: 204);
        }

        return to_route('use-cases.show', $useCase)->with('success', 'Test case removed.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UseCaseRequest;
use App\Http\Resources\UseCaseResource;
use App\Models\Experiment;
use App\Models\UseCase;
use App\Services\ActivityLogService;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UseCaseController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $query = UseCase::query()
            ->with(['creator', 'updater'])
            ->withCount(['promptTemplates', 'testCases']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $useCases = $query->orderBy('name')->get();
        $bestPrompts = $analytics->bestPromptsForUseCases($useCases->pluck('id'));

        $useCases->each(function (UseCase $useCase) use ($bestPrompts): void {
            $useCase->setAttribute('best_prompt', $bestPrompts->get($useCase->id));
        });

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => UseCaseResource::collection($useCases)]);
        }

        return Inertia::render('UseCases/Index', [
            'useCases' => UseCaseResource::collection($useCases)->resolve(),
            'filters' => $request->only(['search', 'status']),
            'canManage' => $request->user()?->canInTeam('manage_use_cases'),
        ]);
    }

    public function show(Request $request, UseCase $useCase, AnalyticsService $analytics): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $useCase->load([
            'creator',
            'updater',
            'promptTemplates' => fn ($query) => $query->withSummaryMetrics(),
            'testCases',
        ]);

        $useCase->setAttribute('best_prompt', $analytics->bestPromptForUseCase($useCase));

        $payload = [
            'useCase' => (new UseCaseResource($useCase))->resolve(),
            'detail' => $analytics->useCaseDetail($useCase),
            'recentExperiments' => Experiment::query()
                ->where('use_case_id', $useCase->id)
                ->with('creator')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (Experiment $experiment) => [
                    'id' => $experiment->id,
                    'mode' => $experiment->mode,
                    'status' => $experiment->status,
                    'model_name' => $experiment->model_name,
                    'created_at' => optional($experiment->created_at)->toIso8601String(),
                    'created_by' => $experiment->creator?->display_name,
                ])
                ->values()
                ->all(),
            'canManage' => $request->user()?->canInTeam('manage_use_cases'),
        ];

        if ($this->isApiRequest($request)) {
            return response()->json($payload);
        }

        return Inertia::render('UseCases/Show', $payload);
    }

    public function store(UseCaseRequest $request, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $useCase = UseCase::create($request->validated() + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $activity->record('use_case.created', $useCase, [
            'name' => $useCase->name,
            'status' => $useCase->status,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new UseCaseResource($useCase),
                'redirect_url' => route('use-cases.show', $useCase),
            ], 201);
        }

        return to_route('use-cases.show', $useCase)->with('success', 'Task created.');
    }

    public function update(UseCaseRequest $request, UseCase $useCase, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $useCase->update($request->validated() + [
            'updated_by' => $request->user()->id,
        ]);
        $activity->record('use_case.updated', $useCase, [
            'name' => $useCase->name,
            'status' => $useCase->status,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => new UseCaseResource($useCase->fresh())]);
        }

        return to_route('use-cases.show', $useCase)->with('success', 'Task updated.');
    }
}

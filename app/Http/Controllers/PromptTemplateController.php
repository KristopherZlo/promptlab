<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptTemplateRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use App\Models\UseCase;
use App\Services\ActivityLogService;
use App\Services\LLMProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptTemplateController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $query = PromptTemplate::query()
            ->with([
                'useCase',
                'creator',
                'versions.creator',
                'versions.libraryEntry.approver',
                'versions.experimentRuns.evaluations',
            ]);

        foreach (['use_case_id', 'task_type', 'status', 'preferred_model'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('author')) {
            $query->whereHas('creator', fn ($builder) => $builder->where('name', 'like', '%'.$request->input('author').'%'));
        }

        $templates = $query->latest()->get();

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => PromptTemplateResource::collection($templates)]);
        }

        return Inertia::render('PromptTemplates/Index', [
            'templates' => PromptTemplateResource::collection($templates)->resolve(),
            'filters' => $request->only(['search', 'use_case_id', 'task_type', 'status', 'author', 'preferred_model']),
            'useCases' => UseCase::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request, LLMProviderManager $providers): Response
    {
        $this->authorizeTeamAbility($request, 'manage_prompts');

        return Inertia::render('PromptTemplates/Edit', [
            'promptTemplate' => null,
            'useCases' => UseCase::orderBy('name')->get(['id', 'name']),
            'models' => $providers->availableModels(),
        ]);
    }

    public function show(Request $request, PromptTemplate $promptTemplate, LLMProviderManager $providers): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $promptTemplate->load([
            'useCase',
            'creator',
            'versions.creator',
            'versions.libraryEntry.approver',
            'versions.experimentRuns.evaluations',
        ]);

        $payload = [
            'promptTemplate' => (new PromptTemplateResource($promptTemplate))->resolve(),
            'useCases' => UseCase::orderBy('name')->get(['id', 'name']),
            'models' => $providers->availableModels(),
        ];

        if ($this->isApiRequest($request)) {
            return response()->json($payload);
        }

        return Inertia::render('PromptTemplates/Edit', $payload);
    }

    public function store(PromptTemplateRequest $request, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $promptTemplate = PromptTemplate::create($request->validated() + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $activity->record('prompt_template.created', $promptTemplate, [
            'name' => $promptTemplate->name,
            'task_type' => $promptTemplate->task_type,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new PromptTemplateResource($promptTemplate),
                'redirect_url' => route('prompt-templates.show', $promptTemplate),
            ], 201);
        }

        return to_route('prompt-templates.show', $promptTemplate)->with('success', 'Prompt template created.');
    }

    public function update(PromptTemplateRequest $request, PromptTemplate $promptTemplate, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $promptTemplate->update($request->validated() + [
            'updated_by' => $request->user()->id,
        ]);
        $activity->record('prompt_template.updated', $promptTemplate, [
            'name' => $promptTemplate->name,
            'task_type' => $promptTemplate->task_type,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => new PromptTemplateResource($promptTemplate->fresh())]);
        }

        return to_route('prompt-templates.show', $promptTemplate)->with('success', 'Prompt template updated.');
    }
}

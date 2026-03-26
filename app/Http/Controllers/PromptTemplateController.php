<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptQuickTestRequest;
use App\Http\Requests\PromptTemplateRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Rules\MaxEncodedSize;
use App\Services\ActivityLogService;
use App\Services\LLMProviderManager;
use App\Services\PromptOptimizationService;
use App\Services\PromptCompiler;
use App\Services\StructuredOutputValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'versions' => fn ($query) => $query
                    ->withSummaryMetrics()
                    ->with(['creator', 'libraryEntry.approver']),
            ]);

        $this->applyIndexFilters($query, $request);

        $templates = $query->latest()->get();

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => PromptTemplateResource::collection($templates)]);
        }

        $collectionCounts = PromptTemplate::query()
            ->select('use_case_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('use_case_id');

        $this->applyIndexFilters($collectionCounts, $request, withUseCase: false);

        $collectionCounts = $collectionCounts
            ->groupBy('use_case_id')
            ->pluck('aggregate', 'use_case_id');

        $useCases = UseCase::orderBy('name')->get(['id', 'name']);
        $categories = PromptTemplate::query()
            ->select('task_type')
            ->whereNotNull('task_type')
            ->where('task_type', '!=', '')
            ->distinct()
            ->orderBy('task_type')
            ->pluck('task_type')
            ->values();
        $collections = $useCases
            ->map(fn (UseCase $useCase) => [
                'id' => $useCase->id,
                'name' => $useCase->name,
                'count' => (int) ($collectionCounts[$useCase->id] ?? 0),
            ])
            ->filter(fn (array $collection) => $collection['count'] > 0)
            ->sort(fn (array $left, array $right) => [$right['count'], $left['name']] <=> [$left['count'], $right['name']])
            ->values();

        return Inertia::render('PromptTemplates/Index', [
            'templates' => PromptTemplateResource::collection($templates)->resolve(),
            'filters' => $request->only(['search', 'use_case_id', 'task_type', 'status', 'author', 'preferred_model']),
            'useCases' => $useCases,
            'categories' => $categories,
            'collections' => $collections,
        ]);
    }

    public function create(Request $request, LLMProviderManager $providers): Response
    {
        $this->authorizeTeamAbility($request, 'manage_prompts');

        return Inertia::render('PromptTemplates/Edit', [
            'promptTemplate' => null,
            'useCases' => UseCase::orderBy('name')->get(['id', 'name']),
            'models' => $providers->availableModels(),
            'optimizationContext' => null,
        ]);
    }

    public function show(
        Request $request,
        PromptTemplate $promptTemplate,
        LLMProviderManager $providers,
        PromptOptimizationService $optimizations,
    ): Response|JsonResponse {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $promptTemplate->load([
            'useCase',
            'useCase.testCases',
            'creator',
            'versions' => fn ($query) => $query
                ->withSummaryMetrics()
                ->with(['creator', 'libraryEntry.approver']),
            'optimizationRuns.creator',
            'optimizationRuns.sourceVersion',
            'optimizationRuns.derivedVersion',
        ]);

        $payload = [
            'promptTemplate' => (new PromptTemplateResource($promptTemplate))->resolve(),
            'useCases' => UseCase::orderBy('name')->get(['id', 'name']),
            'models' => $providers->availableModels(),
            'optimizationContext' => $optimizations->contextForTemplate($promptTemplate),
        ];

        if ($this->isApiRequest($request)) {
            return response()->json($payload);
        }

        return Inertia::render('PromptTemplates/Edit', $payload);
    }

    public function store(PromptTemplateRequest $request, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $templateData = $request->validated();
        $versionData = $request->filled('initial_version')
            ? Validator::make(
                $request->input('initial_version', []),
                $this->initialVersionRules(),
            )->validate()
            : null;

        [$promptTemplate, $firstVersion] = DB::transaction(function () use ($templateData, $versionData, $request, $activity) {
            $promptTemplate = PromptTemplate::create($templateData + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $activity->record('prompt_template.created', $promptTemplate, [
                'name' => $promptTemplate->name,
                'task_type' => $promptTemplate->task_type,
            ], $request->user());

            $firstVersion = null;

            if ($versionData) {
                $versionData['team_id'] = $promptTemplate->team_id;
                $versionData['prompt_template_id'] = $promptTemplate->id;
                $versionData['created_by'] = $request->user()->id;
                $versionData['updated_by'] = $request->user()->id;
                $versionData['version_label'] = $versionData['version_label'] ?: 'v1';

                $firstVersion = PromptVersion::create($versionData);
                $activity->record('prompt_version.created', $firstVersion, [
                    'template_name' => $promptTemplate->name,
                    'version_label' => $firstVersion->version_label,
                    'output_type' => $firstVersion->output_type,
                ], $request->user());
            }

            return [$promptTemplate, $firstVersion];
        });

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new PromptTemplateResource($promptTemplate),
                'redirect_url' => route('prompt-templates.show', $promptTemplate),
                'first_version_id' => $firstVersion?->id,
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

    public function quickTest(
        PromptQuickTestRequest $request,
        LLMProviderManager $providers,
        PromptCompiler $compiler,
        StructuredOutputValidator $validator,
    ): JsonResponse {
        $validated = $request->validated();
        $team = $this->currentTeam($request);
        $useCase = ! empty($validated['use_case_id'])
            ? UseCase::query()->find($validated['use_case_id'])
            : null;

        $template = new PromptTemplate([
            'team_id' => $team->id,
            'use_case_id' => $validated['use_case_id'] ?? null,
            'task_type' => $validated['task_type'] ?? null,
            'preferred_model' => $validated['preferred_model'] ?? $validated['model_name'],
        ]);
        $template->setRelation('useCase', $useCase);

        $version = new PromptVersion([
            'team_id' => $team->id,
            'prompt_template_id' => 0,
            'version_label' => 'draft',
            'system_prompt' => $validated['system_prompt'] ?? null,
            'user_prompt_template' => $validated['user_prompt_template'],
            'variables_schema' => $validated['variables_schema'] ?? [],
            'output_type' => $validated['output_type'],
            'output_schema_json' => $validated['output_schema_json'] ?? [],
            'preferred_model' => $validated['preferred_model'] ?? null,
        ]);
        $version->setRelation('promptTemplate', $template);

        $compiled = $compiler->compile(
            $version,
            (string) ($validated['input_text'] ?? ''),
            $validated['variables'] ?? [],
        );

        if (! empty($compiled['missing'])) {
            throw ValidationException::withMessages([
                'variables' => 'Missing required variables: '.implode(', ', $compiled['missing']).'.',
            ]);
        }

        $providerResponse = $providers->runPrompt($compiled['final_prompt'], [
            'team_id' => $team->id,
            'model' => $validated['model_name'],
            'temperature' => $validated['temperature'],
            'max_tokens' => $validated['max_tokens'],
            'task_type' => $validated['task_type'] ?? 'general',
            'use_case_slug' => $useCase?->slug,
            'output_type' => $validated['output_type'],
            'output_schema' => $validated['output_schema_json'] ?? [],
            'prompt_version_label' => 'draft',
            'system_prompt' => $compiled['system_prompt'],
            'user_prompt' => $compiled['user_prompt'],
        ]);
        $validation = $validator->validate($version, $providerResponse['output_text'] ?? '');

        return response()->json([
            'data' => [
                'output_text' => $providerResponse['output_text'] ?? '',
                'output_json' => $validation['output_json'],
                'compiled_prompt' => $compiled['final_prompt'],
                'format_valid' => $validation['format_valid'],
                'error' => $validation['error'],
                'model_name' => $providerResponse['model_name'] ?? $validated['model_name'],
                'token_input' => $providerResponse['token_input'] ?? 0,
                'token_output' => $providerResponse['token_output'] ?? 0,
                'latency_ms' => $providerResponse['latency_ms'] ?? 0,
            ],
        ]);
    }

    private function initialVersionRules(): array
    {
        return [
            'version_label' => ['nullable', 'string', 'max:64'],
            'change_summary' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:12000'],
            'user_prompt_template' => ['required', 'string', 'max:20000'],
            'variables_schema' => ['nullable', 'array', new MaxEncodedSize(16000)],
            'variables_schema.*.name' => ['required_with:variables_schema', 'string', 'max:64'],
            'variables_schema.*.label' => ['nullable', 'string', 'max:255'],
            'variables_schema.*.required' => ['nullable', 'boolean'],
            'variables_schema.*.default' => ['nullable'],
            'output_type' => ['required', Rule::in(['text', 'json'])],
            'output_schema_json' => ['nullable', 'array', new MaxEncodedSize(12000)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function applyIndexFilters($query, Request $request, bool $withUseCase = true): void
    {
        foreach (['task_type', 'status', 'preferred_model'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($withUseCase && $request->filled('use_case_id')) {
            $query->where('use_case_id', $request->input('use_case_id'));
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
    }
}

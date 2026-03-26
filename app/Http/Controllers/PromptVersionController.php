<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptVersionRequest;
use App\Http\Resources\PromptVersionResource;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PromptVersionController extends Controller
{
    public function store(PromptVersionRequest $request, PromptTemplate $promptTemplate, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['team_id'] = $promptTemplate->team_id;
        $validated['prompt_template_id'] = $promptTemplate->id;
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        $validated['version_label'] = $validated['version_label']
            ?: 'v'.($promptTemplate->versions()->count() + 1);

        $promptVersion = PromptVersion::create($validated);
        $activity->record('prompt_version.created', $promptVersion, [
            'template_name' => $promptTemplate->name,
            'version_label' => $promptVersion->version_label,
            'output_type' => $promptVersion->output_type,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new PromptVersionResource($promptVersion->fresh(['libraryEntry.approver'])),
            ], 201);
        }

        return to_route('prompt-templates.show', $promptTemplate)->with('success', 'Prompt version created.');
    }

    public function update(PromptVersionRequest $request, PromptVersion $promptVersion, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;
        $validated['version_label'] = filled($validated['version_label'] ?? null)
            ? $validated['version_label']
            : $promptVersion->version_label;

        $promptVersion->update($validated);
        $activity->record('prompt_version.updated', $promptVersion, [
            'template_name' => $promptVersion->promptTemplate?->name,
            'version_label' => $promptVersion->version_label,
            'output_type' => $promptVersion->output_type,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json([
                'data' => new PromptVersionResource($promptVersion->fresh(['libraryEntry.approver'])),
            ]);
        }

        return to_route('prompt-templates.show', $promptVersion->promptTemplate)->with('success', 'Prompt version updated.');
    }
}

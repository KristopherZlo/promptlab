<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryEntryRequest;
use App\Http\Resources\LibraryEntryResource;
use App\Models\LibraryEntry;
use App\Models\PromptVersion;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryEntryController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorizeTeamAbility($request, 'view_workspace');

        $entries = LibraryEntry::with(['promptVersion.promptTemplate.useCase', 'approver'])
            ->latest('approved_at')
            ->get();

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => LibraryEntryResource::collection($entries)]);
        }

        return Inertia::render('Library/Index', [
            'entries' => LibraryEntryResource::collection($entries)->resolve(),
            'canManage' => $request->user()?->canInTeam('manage_library'),
        ]);
    }

    public function store(LibraryEntryRequest $request, ActivityLogService $activity): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $promptVersion = PromptVersion::query()->findOrFail($validated['prompt_version_id']);

        $entry = LibraryEntry::updateOrCreate(
            ['prompt_version_id' => $validated['prompt_version_id']],
            $validated + [
                'team_id' => $promptVersion->team_id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]
        );

        PromptVersion::whereKey($validated['prompt_version_id'])->update([
            'is_library_approved' => true,
        ]);
        $activity->record('library.promoted', $entry, [
            'prompt_version_id' => $promptVersion->id,
            'version_label' => $promptVersion->version_label,
            'recommended_model' => $entry->recommended_model,
        ], $request->user());

        if ($this->isApiRequest($request)) {
            return response()->json(['data' => new LibraryEntryResource($entry->fresh(['promptVersion.promptTemplate.useCase', 'approver']))], 201);
        }

        return to_route('library.index')->with('success', 'Prompt promoted to library.');
    }
}

<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;

class WorkspaceJourneyService
{
    public function landingRouteName(): string
    {
        if (! $this->hasCurrentTeam()) {
            return 'getting-started';
        }

        return $this->isEmptyWorkspace() ? 'getting-started' : 'use-cases.index';
    }

    public function landingUrl(): string
    {
        return route($this->landingRouteName());
    }

    public function snapshot(): array
    {
        if (! $this->hasCurrentTeam()) {
            return [
                'stage' => 'empty',
                'is_empty' => true,
                'counts' => [
                    'use_cases' => 0,
                    'prompt_templates' => 0,
                    'runs' => 0,
                    'library_entries' => 0,
                ],
                'latest_use_case' => null,
                'latest_prompt_template' => null,
                'latest_prompt_version' => null,
                'latest_experiment' => null,
                'latest_library_entry' => null,
            ];
        }

        $counts = [
            'use_cases' => UseCase::query()->count(),
            'prompt_templates' => PromptTemplate::query()->count(),
            'runs' => Experiment::query()->count(),
            'library_entries' => LibraryEntry::query()->count(),
        ];

        return [
            'stage' => $this->stageFromCounts($counts),
            'is_empty' => $this->isEmptyCounts($counts),
            'counts' => $counts,
            'latest_use_case' => $this->latestUseCase(),
            'latest_prompt_template' => $this->latestPromptTemplate(),
            'latest_prompt_version' => $this->latestPromptVersion(),
            'latest_experiment' => $this->latestExperiment(),
            'latest_library_entry' => $this->latestLibraryEntry(),
        ];
    }

    private function isEmptyWorkspace(): bool
    {
        return ! UseCase::query()->exists();
    }

    private function hasCurrentTeam(): bool
    {
        return (bool) app(CurrentTeamResolver::class)->currentTeamId();
    }

    private function isEmptyCounts(array $counts): bool
    {
        return ($counts['use_cases'] ?? 0) === 0
            && ($counts['prompt_templates'] ?? 0) === 0
            && ($counts['runs'] ?? 0) === 0
            && ($counts['library_entries'] ?? 0) === 0;
    }

    private function stageFromCounts(array $counts): string
    {
        if (($counts['use_cases'] ?? 0) === 0) {
            return 'empty';
        }

        if (($counts['prompt_templates'] ?? 0) === 0) {
            return 'task_defined';
        }

        if (($counts['runs'] ?? 0) === 0) {
            return 'prompting';
        }

        if (($counts['library_entries'] ?? 0) === 0) {
            return 'testing';
        }

        return 'operating';
    }

    private function latestUseCase(): ?array
    {
        $useCase = UseCase::query()->latest()->first();

        if (! $useCase) {
            return null;
        }

        return [
            'id' => $useCase->id,
            'name' => $useCase->name,
            'status' => $useCase->status,
        ];
    }

    private function latestPromptTemplate(): ?array
    {
        $template = PromptTemplate::query()
            ->with('useCase')
            ->latest()
            ->first();

        if (! $template) {
            return null;
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'use_case_id' => $template->use_case_id,
            'use_case' => $template->useCase?->name,
        ];
    }

    private function latestPromptVersion(): ?array
    {
        $version = PromptVersion::query()
            ->with('promptTemplate.useCase')
            ->latest()
            ->first();

        if (! $version) {
            return null;
        }

        return [
            'id' => $version->id,
            'version_label' => $version->version_label,
            'prompt_template_id' => $version->prompt_template_id,
            'prompt_template' => $version->promptTemplate?->name,
            'use_case_id' => $version->promptTemplate?->use_case_id,
            'use_case' => $version->promptTemplate?->useCase?->name,
        ];
    }

    private function latestExperiment(): ?array
    {
        $experiment = Experiment::query()
            ->with('useCase')
            ->latest()
            ->first();

        if (! $experiment) {
            return null;
        }

        return [
            'id' => $experiment->id,
            'mode' => $experiment->mode,
            'status' => $experiment->status,
            'use_case_id' => $experiment->use_case_id,
            'use_case' => $experiment->useCase?->name,
        ];
    }

    private function latestLibraryEntry(): ?array
    {
        $entry = LibraryEntry::query()
            ->with('promptVersion.promptTemplate.useCase')
            ->latest('approved_at')
            ->first();

        if (! $entry) {
            return null;
        }

        return [
            'id' => $entry->id,
            'prompt_version_id' => $entry->prompt_version_id,
            'prompt_template_id' => $entry->promptVersion?->prompt_template_id,
            'prompt_name' => $entry->promptVersion?->promptTemplate?->name,
            'version_label' => $entry->promptVersion?->version_label,
            'use_case_id' => $entry->promptVersion?->promptTemplate?->use_case_id,
            'use_case' => $entry->promptVersion?->promptTemplate?->useCase?->name,
        ];
    }
}

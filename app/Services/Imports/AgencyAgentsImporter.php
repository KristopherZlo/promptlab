<?php

namespace App\Services\Imports;

use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\Team;
use App\Models\UseCase;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AgencyAgentsImporter
{
    private const EXCLUDED_TOP_LEVEL_DIRECTORIES = [
        '.github',
        'examples',
        'scripts',
        'strategy',
    ];

    public function __construct(
        private readonly ActivityLogService $activity,
    ) {
    }

    public function importForTeam(Team $team, User $user, array $options = []): array
    {
        $source = $this->sourcePath($options['source'] ?? null);
        $refresh = (bool) ($options['refresh'] ?? false);
        $documents = $this->scanDocuments($source, $options['categories'] ?? []);

        $summary = [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'source' => $source,
            'documents' => count($documents),
            'created_use_cases' => 0,
            'created_templates' => 0,
            'created_versions' => 0,
            'created_library_entries' => 0,
            'updated_existing' => 0,
            'skipped_existing' => 0,
        ];

        foreach ($documents as $document) {
            [$useCase, $useCaseCreated, $useCaseUpdated] = $this->upsertUseCase($team, $user, $document, $refresh);
            [$template, $templateCreated, $templateUpdated] = $this->upsertTemplate($useCase, $user, $document, $refresh);
            [$version, $versionCreated, $versionUpdated] = $this->upsertVersion($template, $user, $document, $refresh);
            [$entry, $entryCreated, $entryUpdated] = $this->upsertLibraryEntry($version, $user, $document, $refresh);

            if ($useCaseCreated) {
                $summary['created_use_cases']++;
            }

            if ($templateCreated) {
                $summary['created_templates']++;
            }

            if ($versionCreated) {
                $summary['created_versions']++;
            }

            if ($entryCreated) {
                $summary['created_library_entries']++;
            }

            if ($useCaseUpdated || $templateUpdated || $versionUpdated || $entryUpdated) {
                $summary['updated_existing']++;
            }

            if (! $useCaseCreated && ! $templateCreated && ! $versionCreated && ! $entryCreated && ! $useCaseUpdated && ! $templateUpdated && ! $versionUpdated && ! $entryUpdated) {
                $summary['skipped_existing']++;
            }

            $version->forceFill(['is_library_approved' => true])->save();
        }

        $this->activity->record('library.imported', $team, [
            'source' => 'agency-agents',
            'documents' => $summary['documents'],
            'created_templates' => $summary['created_templates'],
            'created_versions' => $summary['created_versions'],
            'created_library_entries' => $summary['created_library_entries'],
            'updated_existing' => $summary['updated_existing'],
            'skipped_existing' => $summary['skipped_existing'],
        ], $user, $team->id);

        return $summary;
    }

    public function scanDocuments(?string $source = null, array $categories = []): array
    {
        $source = $this->sourcePath($source);
        $normalizedCategories = collect($categories)
            ->filter()
            ->map(fn ($category) => Str::slug((string) $category))
            ->values()
            ->all();

        $documents = [];

        foreach (File::allFiles($source) as $file) {
            if (Str::lower($file->getExtension()) !== 'md') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $topLevelDirectory = Str::before($relativePath, '/');

            if (in_array($topLevelDirectory, self::EXCLUDED_TOP_LEVEL_DIRECTORIES, true)) {
                continue;
            }

            if ($normalizedCategories !== [] && ! in_array(Str::slug($topLevelDirectory), $normalizedCategories, true)) {
                continue;
            }

            $document = $this->parseDocument(
                $file->getRealPath() ?: $file->getPathname(),
                $relativePath,
            );

            if ($document === null) {
                continue;
            }

            $documents[] = $document;
        }

        usort($documents, fn (array $left, array $right) => strcmp($left['relative_path'], $right['relative_path']));

        return $documents;
    }

    private function upsertUseCase(Team $team, User $user, array $document, bool $refresh): array
    {
        $attributes = [
            'name' => $document['use_case_name'],
            'slug' => $document['use_case_slug'],
            'description' => "Reusable specialist prompt collection for {$document['category_label']}.",
            'business_goal' => 'Provide a ready-made specialist prompt catalog inside the shared prompt library.',
            'primary_input_label' => 'Request',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return $this->firstOrRefresh(
            UseCase::query()->where('team_id', $team->id)->where('slug', $document['use_case_slug'])->first(),
            fn () => new UseCase(['team_id' => $team->id, 'slug' => $document['use_case_slug']]),
            $attributes + ['team_id' => $team->id],
            $refresh,
        );
    }

    private function upsertTemplate(UseCase $useCase, User $user, array $document, bool $refresh): array
    {
        $attributes = [
            'team_id' => $useCase->team_id,
            'use_case_id' => $useCase->id,
            'name' => $document['name'],
            'description' => $document['description'],
            'task_type' => 'generation',
            'status' => 'active',
            'preferred_model' => null,
            'tags_json' => $document['tags'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return $this->firstOrRefresh(
            PromptTemplate::query()
                ->where('team_id', $useCase->team_id)
                ->where('use_case_id', $useCase->id)
                ->where('name', $document['name'])
                ->first(),
            fn () => new PromptTemplate([
                'team_id' => $useCase->team_id,
                'use_case_id' => $useCase->id,
                'name' => $document['name'],
            ]),
            $attributes,
            $refresh,
        );
    }

    private function upsertVersion(PromptTemplate $template, User $user, array $document, bool $refresh): array
    {
        $attributes = [
            'team_id' => $template->team_id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Initial library version.',
            'system_prompt' => $document['body'],
            'user_prompt_template' => "Use the operating instructions above to handle the following request.\n\n{{input_text}}",
            'variables_schema' => [
                [
                    'name' => 'input_text',
                    'label' => 'Request',
                    'required' => true,
                ],
            ],
            'output_type' => 'text',
            'output_schema_json' => [],
            'notes' => $document['version_notes'] ?: null,
            'preferred_model' => null,
            'is_library_approved' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return $this->firstOrRefresh(
            PromptVersion::query()
                ->where('team_id', $template->team_id)
                ->where('prompt_template_id', $template->id)
                ->where('version_label', 'v1')
                ->first(),
            fn () => new PromptVersion([
                'team_id' => $template->team_id,
                'prompt_template_id' => $template->id,
                'version_label' => 'v1',
            ]),
            $attributes,
            $refresh,
        );
    }

    private function upsertLibraryEntry(PromptVersion $version, User $user, array $document, bool $refresh): array
    {
        $attributes = [
            'team_id' => $version->team_id,
            'prompt_version_id' => $version->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'recommended_model' => null,
            'best_for' => Str::limit($document['description'], 255, ''),
            'usage_notes' => $document['usage_notes'] ?: null,
        ];

        return $this->firstOrRefresh(
            LibraryEntry::query()->where('prompt_version_id', $version->id)->first(),
            fn () => new LibraryEntry([
                'team_id' => $version->team_id,
                'prompt_version_id' => $version->id,
            ]),
            $attributes,
            $refresh,
        );
    }

    private function firstOrRefresh(object|null $model, callable $factory, array $attributes, bool $refresh): array
    {
        $created = false;
        $updated = false;

        if ($model === null) {
            $model = $factory();
            $model->fill($attributes);
            $model->save();
            $created = true;

            return [$model, $created, $updated];
        }

        if ($refresh) {
            $model->fill($attributes);
            $updated = $model->isDirty();

            if ($updated) {
                $model->save();
            }
        }

        return [$model, $created, $updated];
    }

    private function parseDocument(string $path, string $relativePath): ?array
    {
        $contents = File::get($path);

        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $matches)) {
            return null;
        }

        $frontmatter = $this->parseFrontmatter($matches[1]);
        $body = trim($matches[2]);

        if (! filled($frontmatter['name'] ?? null) || ! filled($frontmatter['description'] ?? null) || $body === '') {
            return null;
        }

        $relativeDirectory = str_replace('\\', '/', dirname($relativePath));

        if ($relativeDirectory === '.' || $relativeDirectory === '') {
            return null;
        }

        $categoryLabel = $this->formatCategoryLabel($relativeDirectory);

        return [
            'name' => trim((string) $frontmatter['name']),
            'description' => trim((string) $frontmatter['description']),
            'body' => $body,
            'relative_path' => $relativePath,
            'relative_directory' => $relativeDirectory,
            'top_level_directory' => Str::before($relativeDirectory, '/'),
            'category_label' => $categoryLabel,
            'use_case_name' => "Agency Agents / {$categoryLabel}",
            'use_case_slug' => Str::limit('agency-agents-'.Str::slug($relativeDirectory), 255, ''),
            'tags' => $this->buildTags($relativeDirectory, $frontmatter),
            'version_notes' => $this->buildVersionNotes($relativePath, $frontmatter),
            'usage_notes' => $this->buildUsageNotes($relativePath, $frontmatter),
        ];
    }

    private function parseFrontmatter(string $frontmatter): array
    {
        $parsed = [];

        foreach (preg_split('/\R/', $frontmatter) ?: [] as $line) {
            if (! preg_match('/^\s*([A-Za-z0-9_-]+):\s*(.*)\s*$/', $line, $matches)) {
                continue;
            }

            $key = Str::snake($matches[1]);
            $value = $this->stripQuotes(trim($matches[2]));

            if ($key === 'tools') {
                $parsed[$key] = collect(explode(',', $value))
                    ->map(fn ($tool) => trim($tool))
                    ->filter()
                    ->values()
                    ->all();

                continue;
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    private function buildTags(string $relativeDirectory, array $frontmatter): array
    {
        $pathTags = collect(explode('/', $relativeDirectory))
            ->map(fn ($segment) => Str::limit(Str::slug($segment), 64, ''))
            ->filter();

        $toolTags = collect($frontmatter['tools'] ?? [])
            ->map(fn ($tool) => Str::limit(Str::slug((string) $tool), 64, ''))
            ->filter();

        return $pathTags
            ->prepend('agency-agents')
            ->concat($toolTags)
            ->unique()
            ->values()
            ->all();
    }

    private function buildVersionNotes(string $relativePath, array $frontmatter): string
    {
        $parts = [];

        if (filled($frontmatter['vibe'] ?? null)) {
            $parts[] = 'Vibe: '.trim((string) $frontmatter['vibe']).'.';
        }

        if (! empty($frontmatter['tools'] ?? [])) {
            $parts[] = 'Tools: '.implode(', ', $frontmatter['tools']).'.';
        }

        return implode(' ', $parts);
    }

    private function buildUsageNotes(string $relativePath, array $frontmatter): string
    {
        $parts = [
            'Use this as a reusable specialist system prompt and provide the concrete task in the request input.',
        ];

        if (filled($frontmatter['vibe'] ?? null)) {
            $parts[] = 'Original vibe: '.trim((string) $frontmatter['vibe']).'.';
        }

        return implode(' ', $parts);
    }

    private function stripQuotes(string $value): string
    {
        if (
            Str::startsWith($value, '"')
            && Str::endsWith($value, '"')
            && Str::length($value) >= 2
        ) {
            return substr($value, 1, -1);
        }

        if (
            Str::startsWith($value, "'")
            && Str::endsWith($value, "'")
            && Str::length($value) >= 2
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function formatCategoryLabel(string $relativeDirectory): string
    {
        return collect(explode('/', $relativeDirectory))
            ->map(function (string $segment): string {
                return Str::of($segment)
                    ->replace(['-', '_'], ' ')
                    ->title()
                    ->toString();
            })
            ->implode(' / ');
    }

    private function sourcePath(?string $source): string
    {
        return $source && $source !== ''
            ? $source
            : storage_path('app/imports/agency-agents');
    }
}

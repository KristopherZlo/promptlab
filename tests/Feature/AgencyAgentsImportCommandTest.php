<?php

namespace Tests\Feature;

use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgencyAgentsImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_agency_agents_markdown_files_into_the_shared_library(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Imported Library Team',
            'description' => 'Workspace for agency-agents import.',
        ]);

        $source = storage_path('framework/testing/agency-agents-fixture');

        File::deleteDirectory($source);
        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'engineering');
        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'design');

        File::put($source.DIRECTORY_SEPARATOR.'engineering'.DIRECTORY_SEPARATOR.'engineering-frontend-developer.md', <<<'MD'
---
name: Frontend Developer
description: Expert frontend developer for responsive interfaces
tools: Read, Write, Edit
vibe: Ships clean interfaces fast.
---

# Frontend Developer

You are the frontend lead for production UI work.
MD);

        File::put($source.DIRECTORY_SEPARATOR.'design'.DIRECTORY_SEPARATOR.'design-ux-researcher.md', <<<'MD'
---
name: UX Researcher
description: User research specialist for evidence-based product decisions
color: green
---

# UX Researcher

You turn interviews and usability findings into concrete recommendations.
MD);

        File::put($source.DIRECTORY_SEPARATOR.'README.md', '# Ignore me');

        try {
            $this->artisan('library:import-agency-agents', [
                '--team' => [$team->id],
                '--user' => $user->id,
                '--source' => $source,
            ])->assertSuccessful();

            $this->assertDatabaseHas('use_cases', [
                'team_id' => $team->id,
                'slug' => 'agency-agents-engineering',
                'name' => 'Agency Agents / Engineering',
            ]);

            $this->assertDatabaseHas('use_cases', [
                'team_id' => $team->id,
                'slug' => 'agency-agents-design',
                'name' => 'Agency Agents / Design',
            ]);

            $this->assertDatabaseHas('prompt_templates', [
                'team_id' => $team->id,
                'name' => 'Frontend Developer',
            ]);

            $template = PromptTemplate::query()
                ->where('team_id', $team->id)
                ->where('name', 'Frontend Developer')
                ->firstOrFail();

            $version = PromptVersion::query()
                ->where('team_id', $team->id)
                ->where('prompt_template_id', $template->id)
                ->where('version_label', 'v1')
                ->firstOrFail();

            $entry = LibraryEntry::query()
                ->where('team_id', $team->id)
                ->where('prompt_version_id', $version->id)
                ->firstOrFail();

            $this->assertTrue($version->is_library_approved);
            $this->assertStringContainsString('Use the operating instructions above', $version->user_prompt_template);
            $this->assertSame('Expert frontend developer for responsive interfaces', $template->description);
            $this->assertStringNotContainsString('agency-agents file', $entry->usage_notes ?? '');
            $this->assertStringContainsString('Use this as a reusable specialist system prompt', $entry->usage_notes ?? '');
            $this->assertSame('Initial library version.', $version->change_summary);
            $this->assertStringNotContainsString('Imported from agency-agents:', $version->notes ?? '');

            $this->artisan('library:import-agency-agents', [
                '--team' => [$team->id],
                '--user' => $user->id,
                '--source' => $source,
            ])->assertSuccessful();

            $this->assertSame(2, UseCase::query()->where('team_id', $team->id)->count());
            $this->assertSame(2, PromptTemplate::query()->where('team_id', $team->id)->count());
            $this->assertSame(2, PromptVersion::query()->where('team_id', $team->id)->count());
            $this->assertSame(2, LibraryEntry::query()->where('team_id', $team->id)->count());
        } finally {
            File::deleteDirectory($source);
        }
    }
}

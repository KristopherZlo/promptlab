<?php

use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use App\Services\Imports\AgencyAgentsImporter;
use App\Services\GepaRuntimeManager;
use App\Services\PromptOptimizationCandidateEvaluator;
use App\Services\PromptOptimizationReflectionService;
use App\Models\PromptOptimizationRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'library:import-agency-agents
        {--team=* : Team IDs to import into. Defaults to all teams.}
        {--user= : User ID recorded as the creator and approver.}
        {--source= : Source directory. Defaults to storage/app/imports/agency-agents.}
        {--category=* : Limit import to one or more top-level categories.}
        {--refresh : Update existing imported prompts from source files.}
        {--dry-run : Scan the source and print what would be imported without writing data.}',
    function (AgencyAgentsImporter $importer): int {
        $teamIds = collect($this->option('team'))->filter()->map(fn ($id) => (int) $id)->values();
        $categories = collect($this->option('category'))->filter()->values()->all();
        $source = $this->option('source') ?: null;

        if ($this->option('dry-run')) {
            $documents = $importer->scanDocuments($source, $categories);

            $this->info('Dry run summary');
            $this->line('Documents found: '.count($documents));
            $this->line('Use-case groups: '.collect($documents)->pluck('use_case_name')->unique()->count());

            foreach (array_slice($documents, 0, 12) as $document) {
                $this->line("- {$document['use_case_name']} :: {$document['name']} ({$document['relative_path']})");
            }

            if (count($documents) > 12) {
                $this->line('...');
            }

            return Command::SUCCESS;
        }

        $teams = $teamIds->isNotEmpty()
            ? Team::query()->whereIn('id', $teamIds)->get()
            : Team::query()->orderBy('id')->get();

        if ($teams->isEmpty()) {
            $this->error('No teams found for import.');

            return Command::FAILURE;
        }

        $requestedUserId = $this->option('user') ? (int) $this->option('user') : null;
        $requestedUser = $requestedUserId ? User::query()->find($requestedUserId) : null;

        if ($requestedUserId && ! $requestedUser) {
            $this->error("User {$requestedUserId} was not found.");

            return Command::FAILURE;
        }

        $totals = [
            'documents' => 0,
            'created_templates' => 0,
            'created_versions' => 0,
            'created_library_entries' => 0,
            'updated_existing' => 0,
            'skipped_existing' => 0,
        ];

        foreach ($teams as $team) {
            $actor = $requestedUser
                ?? User::query()->find($team->created_by)
                ?? $team->users()->orderBy('team_memberships.id')->first()
                ?? User::query()->orderBy('id')->first();

            if (! $actor) {
                $this->warn("Skipping team {$team->id}: no suitable actor was found.");
                continue;
            }

            $summary = $importer->importForTeam($team, $actor, [
                'source' => $source,
                'categories' => $categories,
                'refresh' => (bool) $this->option('refresh'),
            ]);

            $totals['documents'] += $summary['documents'];
            $totals['created_templates'] += $summary['created_templates'];
            $totals['created_versions'] += $summary['created_versions'];
            $totals['created_library_entries'] += $summary['created_library_entries'];
            $totals['updated_existing'] += $summary['updated_existing'];
            $totals['skipped_existing'] += $summary['skipped_existing'];

            $this->info("Imported agency-agents into team {$team->id} ({$team->name})");
            $this->line("  Documents: {$summary['documents']}");
            $this->line("  Templates created: {$summary['created_templates']}");
            $this->line("  Versions created: {$summary['created_versions']}");
            $this->line("  Library entries created: {$summary['created_library_entries']}");
            $this->line("  Existing updated: {$summary['updated_existing']}");
            $this->line("  Existing skipped: {$summary['skipped_existing']}");
        }

        $this->newLine();
        $this->info('Import totals');
        $this->line('  Documents: '.$totals['documents']);
        $this->line('  Templates created: '.$totals['created_templates']);
        $this->line('  Versions created: '.$totals['created_versions']);
        $this->line('  Library entries created: '.$totals['created_library_entries']);
        $this->line('  Existing updated: '.$totals['updated_existing']);
        $this->line('  Existing skipped: '.$totals['skipped_existing']);

        return Command::SUCCESS;
    }
)->purpose('Import the agency-agents prompt library into workspace library entries');

Artisan::command(
    'gepa:install-runtime
        {--reinstall : Reinstall the local embedded Python runtime even if it already exists.}',
    function (GepaRuntimeManager $runtime): int {
        if ($runtime->isInstalled() && ! $this->option('reinstall')) {
            $this->info('GEPA runtime is already installed.');
            $this->line('Python: '.$runtime->pythonBinary());

            return Command::SUCCESS;
        }

        $this->info('Installing embedded GEPA runtime...');

        $runtime->install();

        $this->info('GEPA runtime installed.');
        $this->line('Python: '.$runtime->pythonBinary());

        return Command::SUCCESS;
    }
)->purpose('Install the local embedded Python runtime used for GEPA prompt optimization');

Artisan::command(
    'prompt-optimizations:reflect',
    function (PromptOptimizationReflectionService $reflection): int {
        try {
            $payload = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
            $run = PromptOptimizationRun::query()->findOrFail((int) ($payload['run_id'] ?? 0));
            $output = $reflection->reflect($run, $payload['prompt'] ?? '');

            $this->output->write(json_encode([
                'ok' => true,
                'output' => $output,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->output->write(json_encode([
                'ok' => false,
                'error' => $throwable->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::FAILURE;
        }
    }
)->purpose('Internal GEPA reflection bridge for prompt optimization');

Artisan::command(
    'prompt-optimizations:evaluate-candidate',
    function (PromptOptimizationCandidateEvaluator $evaluator): int {
        try {
            $payload = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
            $run = PromptOptimizationRun::query()->findOrFail((int) ($payload['run_id'] ?? 0));
            $testCase = TestCase::query()
                ->where('team_id', $run->team_id)
                ->findOrFail((int) ($payload['example_id'] ?? 0));
            $result = $evaluator->evaluate($run, $testCase, (array) ($payload['candidate'] ?? []));

            $this->output->write(json_encode([
                'ok' => true,
                'score' => $result['score'],
                'side_info' => $result['side_info'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->output->write(json_encode([
                'ok' => false,
                'error' => $throwable->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::FAILURE;
        }
    }
)->purpose('Internal GEPA evaluator bridge for prompt optimization');

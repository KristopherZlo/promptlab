<?php

namespace Tests\Feature;

use App\Http\Resources\ExperimentResource;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TestCase;
use App\Models\UseCase;
use App\Models\User;
use App\Services\ExperimentService;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase as BaseTestCase;

class ExperimentAutomaticEvaluationTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_batch_experiments_include_automatic_expected_json_results(): void
    {
        Config::set('queue.default', 'sync');
        Config::set('broadcasting.default', 'log');

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Automatic Eval Team',
            'description' => 'Workspace for automatic batch evaluation.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Ticket Categorization',
            'slug' => 'ticket-categorization',
            'description' => 'Classify tickets into strict JSON.',
            'business_goal' => 'Automate routing.',
            'primary_input_label' => 'Ticket text',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Ticket categorizer',
            'description' => 'Batch JSON classifier.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['support', 'json'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v2',
            'change_summary' => 'Strict JSON batch classifier.',
            'system_prompt' => 'Return strict JSON.',
            'user_prompt_template' => 'Classify the ticket into JSON only. {{input_text}}',
            'variables_schema' => [
                ['name' => 'input_text', 'label' => 'Ticket text', 'required' => true],
            ],
            'output_type' => 'json',
            'output_schema_json' => [
                'required' => ['category', 'priority', 'needs_human_review', 'reason'],
                'types' => ['needs_human_review' => 'bool'],
            ],
            'notes' => 'Automatic evaluation test fixture.',
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $matchingCase = TestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'Expected billing category',
            'input_text' => 'The card payment failed twice during renewal.',
            'expected_output' => null,
            'expected_json' => ['category' => 'billing'],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        $failingCase = TestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'Expected wrong category',
            'input_text' => 'The export endpoint times out after thirty seconds.',
            'expected_output' => null,
            'expected_json' => ['category' => 'technical'],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        $experiment = app(ExperimentService::class)->queueBatch($user, [
            'mode' => 'batch',
            'prompt_version_ids' => [$version->id],
            'test_case_ids' => [$matchingCase->id, $failingCase->id],
            'variables' => [],
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.1,
            'max_tokens' => 500,
        ]);

        $payload = (new ExperimentResource($experiment))->resolve();

        $this->assertEquals(50.0, $payload['summary']['automatic_pass_rate']);
        $this->assertSame(2, $payload['summary']['automatic_evaluated_runs']);

        $runsByTitle = collect($payload['runs'])
            ->map(fn ($run) => is_array($run) ? $run : $run->resolve())
            ->keyBy(fn (array $run) => $run['test_case']['title']);

        $passingRun = $runsByTitle->get('Expected billing category');
        $failingRun = $runsByTitle->get('Expected wrong category');

        $this->assertTrue($passingRun['automatic_evaluation']['configured']);
        $this->assertTrue($passingRun['automatic_evaluation']['passed']);
        $this->assertSame(1, $passingRun['automatic_evaluation']['passed_checks']);

        $this->assertTrue($failingRun['automatic_evaluation']['configured']);
        $this->assertFalse($failingRun['automatic_evaluation']['passed']);
        $this->assertStringContainsString('expected \'technical\'', $failingRun['automatic_evaluation']['checks'][0]['message']);
    }
}

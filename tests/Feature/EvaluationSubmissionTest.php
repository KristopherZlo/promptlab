<?php

namespace Tests\Feature;

use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_runs_cannot_be_manually_evaluated(): void
    {
        [$user, $run] = $this->reviewFixture('queued');

        $this->actingAs($user)
            ->postJson(route('api.evaluations.store'), [
                'experiment_run_id' => $run->id,
                'clarity_score' => 4,
                'correctness_score' => 4,
                'completeness_score' => 4,
                'tone_score' => 4,
                'format_valid_manual' => true,
                'hallucination_risk' => 'low',
                'notes' => 'Should be blocked until the run finishes.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['experiment_run_id']);
    }

    public function test_invalid_format_runs_can_still_be_manually_evaluated(): void
    {
        [$user, $run] = $this->reviewFixture('invalid_format', [
            'output_text' => '{"summary": "missing required keys"}',
            'format_valid' => false,
            'error_message' => 'Missing required keys: priority',
        ]);

        $this->actingAs($user)
            ->postJson(route('api.evaluations.store'), [
                'experiment_run_id' => $run->id,
                'clarity_score' => 2,
                'correctness_score' => 3,
                'completeness_score' => 1,
                'tone_score' => 4,
                'format_valid_manual' => false,
                'hallucination_risk' => 'medium',
                'notes' => 'Output exists, but it should be fixed before reuse.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.experiment_run_id', $run->id);
    }

    private function reviewFixture(string $runStatus, array $runOverrides = []): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Evaluation Team',
            'description' => 'Workspace for evaluation submission tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Evaluation fixture',
            'slug' => 'evaluation-fixture',
            'description' => 'Fixture for evaluation tests.',
            'business_goal' => 'Review experiment outputs safely.',
            'primary_input_label' => 'Input',
            'status' => 'active',
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Evaluation prompt',
            'description' => 'Prompt for evaluation tests.',
            'task_type' => 'classification',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['evaluation'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Evaluation fixture.',
            'system_prompt' => 'Classify customer requests.',
            'user_prompt_template' => '{{input_text}}',
            'variables_schema' => [],
            'output_type' => 'json',
            'output_schema_json' => ['priority' => 'string'],
            'notes' => 'Evaluation fixture version.',
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $experiment = Experiment::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'created_by' => $user->id,
            'mode' => 'single',
            'provider' => 'mock',
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.2,
            'max_tokens' => 500,
            'prompt_version_ids_json' => [$version->id],
            'input_text' => 'Customer says the invoice is wrong.',
            'variables_json' => [],
            'summary_json' => [],
            'status' => $runStatus === 'queued' ? 'queued' : 'completed',
            'total_runs' => 1,
            'completed_runs' => $runStatus === 'queued' ? 0 : 1,
            'failed_runs' => $runStatus === 'failed' ? 1 : 0,
        ]);

        $run = ExperimentRun::create(array_merge([
            'team_id' => $team->id,
            'experiment_id' => $experiment->id,
            'prompt_version_id' => $version->id,
            'input_text' => 'Customer says the invoice is wrong.',
            'variables_json' => [],
            'status' => $runStatus,
            'compiled_prompt' => $runStatus === 'queued' ? null : 'SYSTEM: Classify customer requests.',
            'output_text' => null,
            'format_valid' => null,
            'error_message' => null,
        ], $runOverrides));

        return [$user, $run];
    }
}

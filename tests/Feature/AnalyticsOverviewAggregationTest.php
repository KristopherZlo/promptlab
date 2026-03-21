<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsOverviewAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_uses_summary_aggregates_for_top_prompts_and_models(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Analytics Team',
            'description' => 'Workspace for analytics aggregates.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Analytics',
            'slug' => 'analytics',
            'description' => 'Analytics fixture.',
            'business_goal' => 'Verify SQL aggregates.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Analytics Prompt',
            'description' => 'Aggregate fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Initial version.',
            'system_prompt' => 'Summarize clearly.',
            'user_prompt_template' => 'Summarize {{input_text}}',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
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
            'input_text' => 'Aggregate this.',
            'variables_json' => [],
            'summary_json' => [],
            'status' => 'completed',
            'total_runs' => 1,
            'completed_runs' => 1,
            'failed_runs' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $run = ExperimentRun::create([
            'team_id' => $team->id,
            'experiment_id' => $experiment->id,
            'prompt_version_id' => $version->id,
            'input_text' => 'Aggregate this.',
            'variables_json' => [],
            'compiled_prompt' => 'Summarize this.',
            'output_text' => 'Summary output.',
            'format_valid' => true,
            'status' => 'success',
        ]);

        Evaluation::create([
            'team_id' => $team->id,
            'experiment_run_id' => $run->id,
            'evaluator_id' => $user->id,
            'clarity_score' => 4,
            'correctness_score' => 5,
            'completeness_score' => 4,
            'tone_score' => 5,
            'format_valid_manual' => true,
            'hallucination_risk' => 'low',
            'notes' => 'Looks good.',
        ]);

        $this->actingAs($user)
            ->getJson(route('api.analytics.overview'))
            ->assertOk()
            ->assertJsonPath('top_performing_prompts.0.id', $version->id)
            ->assertJsonPath('top_performing_prompts.0.average_score', 4.5)
            ->assertJsonPath('top_models.0.model_name', 'mock:team-lab-v1')
            ->assertJsonPath('top_models.0.average_score', 4.5);
    }
}

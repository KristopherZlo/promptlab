<?php

namespace Tests\Feature;

use App\Models\PromptOptimizationRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\TestCase as PromptTestCase;
use App\Models\UseCase;
use App\Models\User;
use App\Services\GepaPromptOptimizer;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class PromptOptimizationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_editor_can_start_gepa_optimization_and_receive_a_derived_draft(): void
    {
        Config::set('queue.default', 'sync');
        Config::set('broadcasting.default', 'log');

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'GEPA Team',
            'description' => 'Workspace for prompt optimization tests.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Support summarization',
            'slug' => 'support-summarization',
            'description' => 'Summarize customer support messages.',
            'business_goal' => 'Improve support triage consistency.',
            'primary_input_label' => 'Customer message',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Support summarizer',
            'description' => 'Summarizes inbound support issues.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['support'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Baseline support summary prompt.',
            'system_prompt' => 'You summarize support issues.',
            'user_prompt_template' => 'Summarize {{input_text}} into a short triage note.',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'notes' => 'Baseline version.',
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PromptTestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'Payment retry issue',
            'input_text' => 'The payment failed twice and the customer needs an answer today.',
            'expected_output' => 'customer',
            'expected_json' => [],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        PromptTestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'Escalation issue',
            'input_text' => 'Please escalate this billing failure to the support lead.',
            'expected_output' => 'support',
            'expected_json' => [],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        $optimizer = Mockery::mock(GepaPromptOptimizer::class);
        $optimizer->shouldReceive('optimize')
            ->once()
            ->andReturn([
                'ok' => true,
                'best_candidate' => [
                    'system_prompt' => 'You summarize support issues and make the next action explicit.',
                    'user_prompt_template' => 'Summarize {{input_text}} into three bullets and a recommended next action.',
                ],
                'best_score' => 1.0,
                'total_metric_calls' => 12,
                'candidate_count' => 4,
                'result' => [
                    'best_idx' => 1,
                    'candidates' => [
                        [
                            'system_prompt' => 'You summarize support issues.',
                            'user_prompt_template' => 'Summarize {{input_text}} into a short triage note.',
                        ],
                        [
                            'system_prompt' => 'You summarize support issues and make the next action explicit.',
                            'user_prompt_template' => 'Summarize {{input_text}} into three bullets and a recommended next action.',
                        ],
                    ],
                    'val_aggregate_scores' => [0.4, 1.0],
                    'total_metric_calls' => 12,
                ],
            ]);
        $this->app->instance(GepaPromptOptimizer::class, $optimizer);

        $response = $this->actingAs($user)
            ->post(route('api.prompt-optimizations.store', $template), [
                'source_prompt_version_id' => $version->id,
                'model_name' => 'mock:team-lab-v1',
                'budget_metric_calls' => 12,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.best_score', 1)
            ->assertJsonPath('data.derived_version.version_label', 'v2');

        $this->assertDatabaseHas('prompt_optimization_runs', [
            'prompt_template_id' => $template->id,
            'source_prompt_version_id' => $version->id,
            'status' => 'completed',
            'requested_model_name' => 'mock:team-lab-v1',
        ]);

        $run = PromptOptimizationRun::query()->first();

        $this->assertNotNull($run);
        $this->assertNotNull($run->derived_prompt_version_id);

        $this->assertDatabaseHas('prompt_versions', [
            'id' => $run->derived_prompt_version_id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v2',
            'change_summary' => 'GEPA optimization draft from v1',
            'preferred_model' => 'mock:team-lab-v1',
        ]);

        $this->actingAs($user)
            ->get("/prompts/{$template->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PromptTemplates/Edit')
                ->where('optimizationContext.eligible_test_case_count', 2)
                ->where('optimizationContext.runs.0.status', 'completed')
                ->where('optimizationContext.runs.0.derived_version.version_label', 'v2')
            );
    }

    public function test_optimization_requires_active_expected_test_cases(): void
    {
        Config::set('queue.default', 'sync');

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Empty Eval Team',
            'description' => 'Workspace for empty optimization dataset test.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Drafting',
            'slug' => 'drafting',
            'description' => 'Draft replies.',
            'business_goal' => 'Speed up writing.',
            'primary_input_label' => 'Customer message',
            'status' => 'active',
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Reply drafter',
            'description' => 'Drafts replies.',
            'task_type' => 'rewrite',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['reply'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $version = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Baseline.',
            'system_prompt' => 'Write reply drafts.',
            'user_prompt_template' => 'Draft a reply to {{input_text}}.',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PromptTestCase::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'title' => 'No expectations',
            'input_text' => 'Please reply politely.',
            'expected_output' => null,
            'expected_json' => [],
            'variables_json' => [],
            'metadata_json' => [],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson(route('api.prompt-optimizations.store', $template), [
                'source_prompt_version_id' => $version->id,
                'model_name' => 'mock:team-lab-v1',
                'budget_metric_calls' => 12,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_prompt_version_id']);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ExecuteExperimentRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExperimentQueueFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_experiments_are_created_queued_and_dispatched_without_inline_execution(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Queued Experiments Team',
            'description' => 'Workspace for async experiments.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Queued Compare',
            'slug' => 'queued-compare',
            'description' => 'Compare flow fixture.',
            'business_goal' => 'Avoid inline execution.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Compare Prompt',
            'description' => 'Experiment queue fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $versions = collect(['v1', 'v2'])->map(fn (string $label) => PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $template->id,
            'version_label' => $label,
            'change_summary' => "Revision {$label}.",
            'system_prompt' => 'Summarize clearly.',
            'user_prompt_template' => 'Summarize {{input_text}}',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        $response = $this->actingAs($user)
            ->postJson(route('api.experiments.store'), [
                'mode' => 'compare',
                'prompt_version_ids' => $versions->pluck('id')->all(),
                'input_text' => 'Queue this request instead of running inline.',
                'variables' => [],
                'model_name' => 'mock:team-lab-v1',
                'temperature' => 0.2,
                'max_tokens' => 500,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.completed_runs', 0)
            ->assertJsonPath('data.failed_runs', 0)
            ->assertJsonPath('data.total_runs', 2);

        $this->assertDatabaseHas('experiments', [
            'team_id' => $team->id,
            'mode' => 'compare',
            'status' => 'queued',
            'started_at' => null,
            'completed_runs' => 0,
            'failed_runs' => 0,
        ]);

        $this->assertDatabaseCount('experiment_runs', 2);
        $this->assertDatabaseHas('experiment_runs', [
            'status' => 'queued',
            'compiled_prompt' => null,
            'output_text' => null,
        ]);

        Queue::assertPushed(ExecuteExperimentRun::class, 2);
    }
}

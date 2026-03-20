<?php

namespace Tests\Feature;

use App\Exceptions\RetryableOperationException;
use App\Exceptions\TerminalOperationException;
use App\Jobs\ExecuteExperimentRun;
use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\ExperimentService;
use App\Services\LLMProviderManager;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ExperimentJobSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_retryable_failures_return_runs_to_queue_until_job_is_terminally_failed(): void
    {
        [$run] = $this->runFixture();

        $providers = Mockery::mock(LLMProviderManager::class);
        $providers->shouldReceive('runPrompt')
            ->once()
            ->andThrow(new RetryableOperationException('Provider temporarily unavailable.'));
        $this->app->instance(LLMProviderManager::class, $providers);

        $job = new ExecuteExperimentRun($run->id);

        try {
            $job->handle($this->app->make(ExperimentService::class));
            $this->fail('Retryable failures should be rethrown to the queue worker.');
        } catch (RetryableOperationException) {
            $this->assertDatabaseHas('experiment_runs', [
                'id' => $run->id,
                'status' => 'queued',
                'error_message' => 'Provider temporarily unavailable.',
            ]);
        }

        $job->failed(new RetryableOperationException('Provider retries exhausted.'));

        $this->assertDatabaseHas('experiment_runs', [
            'id' => $run->id,
            'status' => 'failed',
            'error_message' => 'Provider retries exhausted.',
        ]);
    }

    public function test_terminal_failures_are_marked_failed_without_retrying(): void
    {
        [$run] = $this->runFixture();

        $providers = Mockery::mock(LLMProviderManager::class);
        $providers->shouldReceive('runPrompt')
            ->once()
            ->andThrow(new TerminalOperationException('The selected model is invalid.'));
        $this->app->instance(LLMProviderManager::class, $providers);

        $job = new ExecuteExperimentRun($run->id);
        $job->handle($this->app->make(ExperimentService::class));

        $this->assertDatabaseHas('experiment_runs', [
            'id' => $run->id,
            'status' => 'failed',
            'error_message' => 'The selected model is invalid.',
        ]);
    }

    private function runFixture(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Job Semantics Team',
            'description' => 'Workspace for queue failure semantics.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Job Semantics',
            'slug' => 'job-semantics',
            'description' => 'Queue semantics fixture.',
            'business_goal' => 'Classify provider failures.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Retry Prompt',
            'description' => 'Queue failure fixture.',
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
            'input_text' => 'Trigger queue semantics.',
            'variables_json' => [],
            'summary_json' => [],
            'status' => 'queued',
            'total_runs' => 1,
            'completed_runs' => 0,
            'failed_runs' => 0,
        ]);

        $run = ExperimentRun::create([
            'team_id' => $team->id,
            'experiment_id' => $experiment->id,
            'prompt_version_id' => $version->id,
            'input_text' => 'Trigger queue semantics.',
            'variables_json' => [],
            'status' => 'queued',
        ]);

        return [$run, $experiment];
    }
}

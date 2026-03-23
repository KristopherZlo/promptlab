<?php

namespace Tests\Feature;

use App\Models\Experiment;
use App\Models\ExperimentRun;
use App\Models\LibraryEntry;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromptLabSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_render_core_promptlab_pages(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Smoke Test Team',
            'description' => 'Workspace for PromptLab smoke coverage.',
        ]);

        $useCase = UseCase::create([
            'team_id' => $team->id,
            'name' => 'Customer Email Summarization',
            'slug' => 'customer-email-summarization',
            'description' => 'Summarize long support emails.',
            'business_goal' => 'Reduce triage time for support agents.',
            'primary_input_label' => 'Customer message',
            'status' => 'active',
        ]);

        $promptTemplate = PromptTemplate::create([
            'team_id' => $team->id,
            'use_case_id' => $useCase->id,
            'name' => 'Customer email summarizer',
            'description' => 'Reusable summarization prompt container.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => ['support'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $promptVersion = PromptVersion::create([
            'team_id' => $team->id,
            'prompt_template_id' => $promptTemplate->id,
            'version_label' => 'v1',
            'change_summary' => 'Initial structured summary version.',
            'system_prompt' => 'You are a support assistant.',
            'user_prompt_template' => 'Summarize {{input_text}} into bullet points.',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'notes' => 'Baseline prompt.',
            'preferred_model' => 'mock:team-lab-v1',
            'is_library_approved' => true,
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
            'max_tokens' => 700,
            'prompt_version_ids_json' => [$promptVersion->id],
            'variables_json' => [],
            'summary_json' => [],
            'status' => 'completed',
            'total_runs' => 1,
            'completed_runs' => 1,
            'failed_runs' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ExperimentRun::create([
            'team_id' => $team->id,
            'experiment_id' => $experiment->id,
            'prompt_version_id' => $promptVersion->id,
            'input_text' => 'Customer cannot complete a payment after two retries.',
            'variables_json' => [],
            'compiled_prompt' => 'SYSTEM: You are a support assistant.',
            'output_text' => 'Customer reports a payment failure.',
            'latency_ms' => 180,
            'token_input' => 52,
            'token_output' => 28,
            'format_valid' => true,
            'status' => 'success',
        ]);

        $libraryEntry = LibraryEntry::create([
            'team_id' => $team->id,
            'prompt_version_id' => $promptVersion->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'recommended_model' => 'mock:team-lab-v1',
            'best_for' => 'Customer support triage',
            'usage_notes' => 'Use for inbound support messages with clear issue statements.',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($user)
            ->get('/acknowledgements')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Acknowledgements')
                ->where('sources.0.name', 'agency-agents')
                ->where('sources.0.author', 'msitarzewski')
            );

        $this->actingAs($user)
            ->get('/use-cases')
            ->assertOk();

        $this->actingAs($user)
            ->get("/use-cases/{$useCase->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('UseCases/Show')
                ->where('useCase.prompt_templates.0.id', $promptTemplate->id)
                ->where('useCase.prompt_templates.0.name', 'Customer email summarizer')
                ->where('useCase.test_cases', [])
            );

        $this->actingAs($user)
            ->get('/prompts')
            ->assertOk();

        $this->actingAs($user)
            ->get("/prompts/{$promptTemplate->id}")
            ->assertOk();

        $this->actingAs($user)
            ->get('/playground')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/users-access')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/workspaces')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/ai-connections')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/audit-log')
            ->assertOk();

        $this->actingAs($user)
            ->get('/team-workspace')
            ->assertRedirect(route('admin.workspaces', absolute: false));

        $this->actingAs($user)
            ->get("/experiments/{$experiment->id}")
            ->assertOk();

        $this->actingAs($user)
            ->get('/library')
            ->assertOk();

        $this->actingAs($user)
            ->get("/library/{$libraryEntry->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Library/Show')
                ->where('entry.id', $libraryEntry->id)
                ->where('entry.prompt_version.id', $promptVersion->id)
            );
    }
}

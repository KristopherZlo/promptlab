<?php

namespace Tests\Feature;

use App\Models\Experiment;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\UseCase;
use App\Models\User;
use App\Services\TeamPermissionService;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Facade;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class ExperimentChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'test-key');
        config()->set('broadcasting.connections.pusher.secret', 'test-secret');
        config()->set('broadcasting.connections.pusher.app_id', 'test-app');
        config()->set('broadcasting.connections.pusher.options', []);

        app()->forgetInstance('broadcast.manager');
        Facade::clearResolvedInstance('broadcast');

        require base_path('routes/channels.php');
    }

    public function test_same_workspace_member_can_authorize_experiment_channel(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_TEAM_MEMBER]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Realtime Team',
            'description' => 'Workspace for broadcast auth.',
        ]);
        app(TeamProvisioningService::class)->addMember($team, $owner, $member->email, TeamPermissionService::ROLE_REVIEWER);
        $member = $member->fresh();
        $this->assertTrue($member->canInTeam('view_workspace', $team->id));

        $experiment = $this->createExperimentFixture($team->id, $owner->id);

        $result = $this->authorizeChannel($member, $experiment->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth', $result);
    }

    public function test_foreign_workspace_member_cannot_authorize_experiment_channel(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $foreignUser = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Secure Team',
            'description' => 'Workspace with private experiments.',
        ]);
        app(TeamProvisioningService::class)->createTeam($foreignUser, [
            'name' => 'Foreign Team',
            'description' => 'Workspace without access.',
        ]);

        $experiment = $this->createExperimentFixture($team->id, $owner->id);

        $this->expectException(AccessDeniedHttpException::class);

        $this->authorizeChannel($foreignUser, $experiment->id);
    }

    private function createExperimentFixture(int $teamId, int $userId): Experiment
    {
        $useCase = UseCase::create([
            'team_id' => $teamId,
            'name' => 'Channel Auth',
            'slug' => 'channel-auth',
            'description' => 'Realtime authorization fixture.',
            'business_goal' => 'Protect experiment streams.',
            'primary_input_label' => 'Input',
            'status' => 'active',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $template = PromptTemplate::create([
            'team_id' => $teamId,
            'use_case_id' => $useCase->id,
            'name' => 'Protected Prompt',
            'description' => 'Broadcast auth fixture.',
            'task_type' => 'summarization',
            'status' => 'active',
            'preferred_model' => 'mock:team-lab-v1',
            'tags_json' => [],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $version = PromptVersion::create([
            'team_id' => $teamId,
            'prompt_template_id' => $template->id,
            'version_label' => 'v1',
            'change_summary' => 'Initial version.',
            'system_prompt' => 'Summarize clearly.',
            'user_prompt_template' => 'Summarize {{input_text}}',
            'variables_schema' => [],
            'output_type' => 'text',
            'output_schema_json' => [],
            'preferred_model' => 'mock:team-lab-v1',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return Experiment::create([
            'team_id' => $teamId,
            'use_case_id' => $useCase->id,
            'created_by' => $userId,
            'mode' => 'single',
            'provider' => 'mock',
            'model_name' => 'mock:team-lab-v1',
            'temperature' => 0.2,
            'max_tokens' => 512,
            'prompt_version_ids_json' => [$version->id],
            'input_text' => 'Realtime auth',
            'variables_json' => [],
            'summary_json' => [],
            'status' => 'queued',
            'total_runs' => 1,
            'completed_runs' => 0,
            'failed_runs' => 0,
        ]);
    }

    private function authorizeChannel(User $user, int $experimentId): array
    {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-experiments.{$experimentId}",
        ]);
        $request->setUserResolver(fn () => $user);

        $broadcaster = Broadcast::connection();
        $method = new ReflectionMethod($broadcaster, 'verifyUserCanAccessChannel');
        $method->setAccessible(true);

        return $method->invoke($broadcaster, $request, "experiments.{$experimentId}");
    }
}

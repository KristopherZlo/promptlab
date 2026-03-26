<?php

namespace Tests\Feature;

use App\Models\TeamMembership;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_web_routes_render_the_branded_404_page(): void
    {
        $response = $this->get('/missing-evala-page');

        $response->assertNotFound();
        $response->assertSee('Evala');
        $response->assertSee('Page not found');
        $response->assertDontSee('Sorry, the page you are looking for could not be found.');
    }

    public function test_forbidden_workspace_pages_render_the_branded_403_page(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        $editor = User::factory()->create([
            'role' => User::ROLE_TEAM_MEMBER,
            'email_verified_at' => now(),
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($owner, [
            'name' => 'Errors Team',
            'description' => 'Workspace for branded error page coverage.',
        ]);

        TeamMembership::create([
            'team_id' => $team->id,
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);

        $editor->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($editor)->get('/admin/workspaces');

        $response->assertForbidden();
        $response->assertSee('Evala');
        $response->assertSee('Access denied');
        $response->assertDontSee('Forbidden');
    }

    public function test_health_endpoint_returns_custom_json_instead_of_vendor_html(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson([
            'status' => 'ok',
            'app' => 'Evala',
        ]);
    }
}

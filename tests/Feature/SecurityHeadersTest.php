<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_security_headers(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Headers Team',
            'description' => 'Workspace for security header checks.',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'")
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_secure_requests_include_hsts_header(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'HSTS Team',
            'description' => 'Workspace for HSTS checks.',
        ]);

        $this->actingAs($user)
            ->withServerVariables(['HTTPS' => 'on'])
            ->get('https://localhost/dashboard')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}

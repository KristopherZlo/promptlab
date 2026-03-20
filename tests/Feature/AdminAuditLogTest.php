<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TeamProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_supports_date_sorting_and_pagination(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Audit Team',
        ]);

        $start = Carbon::parse('2026-03-01 12:00:00');

        foreach (range(1, 25) as $index) {
            $this->createAuditEntry(
                user: $user,
                teamId: $team->id,
                action: 'task.updated',
                subjectLabel: sprintf('Entry %02d', $index),
                createdAt: $start->copy()->addMinutes($index),
            );
        }

        $this->actingAs($user)
            ->get('/admin/audit-log?sort=oldest&page=2&action=task.updated')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLog')
                ->where('filters.action', 'task.updated')
                ->where('filters.sort', 'oldest')
                ->where('entries.meta.current_page', 2)
                ->where('entries.meta.last_page', 2)
                ->where('entries.meta.total', 25)
                ->where('entries.meta.from', 21)
                ->where('entries.meta.to', 25)
                ->where('entries.data.0.subject_label', 'Entry 21')
                ->where('entries.data.4.subject_label', 'Entry 25')
            );
    }

    public function test_audit_log_applies_search_and_action_filters_server_side(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $team = app(TeamProvisioningService::class)->createTeam($user, [
            'name' => 'Filtered Audit Team',
        ]);

        $baseTime = Carbon::parse('2026-03-02 09:00:00');

        $this->createAuditEntry(
            user: $user,
            teamId: $team->id,
            action: 'member.invited',
            subjectLabel: 'Alpha invite sent',
            createdAt: $baseTime->copy()->addMinute(),
        );

        $this->createAuditEntry(
            user: $user,
            teamId: $team->id,
            action: 'member.invited',
            subjectLabel: 'Beta invite sent',
            createdAt: $baseTime->copy()->addMinutes(2),
        );

        $this->createAuditEntry(
            user: $user,
            teamId: $team->id,
            action: 'workspace.updated',
            subjectLabel: 'Alpha workspace renamed',
            createdAt: $baseTime->copy()->addMinutes(3),
        );

        $this->actingAs($user)
            ->get('/admin/audit-log?action=member.invited&search=Alpha')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLog')
                ->where('filters.action', 'member.invited')
                ->where('filters.search', 'Alpha')
                ->where('entries.meta.total', 1)
                ->where('entries.data.0.action', 'member.invited')
                ->where('entries.data.0.subject_label', 'Alpha invite sent')
            );
    }

    private function createAuditEntry(User $user, int $teamId, string $action, string $subjectLabel, Carbon $createdAt): void
    {
        $entry = ActivityLog::create([
            'team_id' => $teamId,
            'actor_id' => $user->id,
            'action' => $action,
            'subject_label' => $subjectLabel,
            'details_json' => ['source' => 'test'],
        ]);

        $entry->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }
}

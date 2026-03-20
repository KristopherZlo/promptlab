<?php

namespace App\Http\Requests;

use App\Rules\AllowedWorkspaceModel;
use App\Services\AllowedModelService;
use App\Services\CurrentTeamResolver;
use App\Services\TeamPermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TeamAwareRequest extends FormRequest
{
    protected function authorizeAbility(string $ability): bool
    {
        return app(TeamPermissionService::class)->can($this->user(), $ability);
    }

    protected function currentTeamId(): ?int
    {
        return app(CurrentTeamResolver::class)->currentTeamId($this->user());
    }

    protected function teamScopedExists(string $table, string $column = 'id')
    {
        $rule = Rule::exists($table, $column);
        $teamId = $this->currentTeamId();

        if ($teamId !== null) {
            $rule = $rule->where(fn ($query) => $query->where('team_id', $teamId));
        }

        return $rule;
    }

    protected function allowedWorkspaceModel(): AllowedWorkspaceModel
    {
        return new AllowedWorkspaceModel(
            app(AllowedModelService::class),
            $this->currentTeamId(),
        );
    }
}

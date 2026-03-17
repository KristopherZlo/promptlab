<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Services\CurrentTeamResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCurrentTeam
{
    public static function bootBelongsToCurrentTeam(): void
    {
        static::addGlobalScope('current_team', function (Builder $builder): void {
            $teamId = app(CurrentTeamResolver::class)->currentTeamId();

            if ($teamId) {
                $builder->where($builder->qualifyColumn('team_id'), $teamId);
            }
        });

        static::creating(function ($model): void {
            if (! filled($model->team_id)) {
                $teamId = app(CurrentTeamResolver::class)->currentTeamId();

                if ($teamId) {
                    $model->team_id = $teamId;
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

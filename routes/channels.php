<?php

use App\Models\Experiment;
use App\Services\TeamPermissionService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('experiments.{experimentId}', function ($user, $experimentId) {
    if (! $user) {
        return false;
    }

    $experiment = Experiment::withoutGlobalScopes()->find($experimentId);

    if (! $experiment) {
        return false;
    }

    return app(TeamPermissionService::class)->can($user, 'view_workspace', $experiment->team_id);
});

<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogService
{
    public function record(string $action, ?Model $subject = null, array $details = [], ?User $actor = null, ?int $teamId = null): ActivityLog
    {
        $teamId ??= $subject?->getAttribute('team_id');
        $teamId ??= app(CurrentTeamResolver::class)->currentTeamId($actor);

        return ActivityLog::create([
            'team_id' => $teamId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $this->resolveSubjectLabel($subject, $details),
            'details_json' => $details ?: null,
        ]);
    }

    private function resolveSubjectLabel(?Model $subject, array $details): ?string
    {
        if (! $subject) {
            return Arr::get($details, 'label');
        }

        foreach (['name', 'title', 'version_label', 'email'] as $attribute) {
            if (filled($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return Str::afterLast($subject::class, '\\').' #'.$subject->getKey();
    }
}

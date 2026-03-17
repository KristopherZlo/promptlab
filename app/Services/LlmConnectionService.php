<?php

namespace App\Services;

use App\Models\LlmConnection;
use App\Models\Team;
use App\Models\User;

class LlmConnectionService
{
    public function __construct(
        private readonly ActivityLogService $activity,
    ) {
    }

    public function save(Team $team, User $actor, array $data, ?LlmConnection $connection = null): LlmConnection
    {
        if (($data['is_default'] ?? false) === true) {
            LlmConnection::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->update(['is_default' => false]);
        }

        $payload = [
            'team_id' => $team->id,
            'name' => $data['name'],
            'driver' => $data['driver'],
            'base_url' => $data['base_url'] ?: null,
            'models_json' => $data['models_json'] ?? [],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'updated_by' => $actor->id,
        ];

        if ($connection === null) {
            $payload['created_by'] = $actor->id;
        }

        if (filled($data['api_key'] ?? null)) {
            $payload['api_key'] = $data['api_key'];
        }

        $connection = $connection
            ? tap($connection)->update($payload)
            : LlmConnection::create($payload);

        $this->activity->record(
            $connection->wasRecentlyCreated ? 'connection.created' : 'connection.updated',
            $connection,
            [
                'name' => $connection->name,
                'driver' => $connection->driver,
                'models' => $connection->models_json ?? [],
                'is_active' => $connection->is_active,
                'is_default' => $connection->is_default,
            ],
            $actor,
            $team->id,
        );

        return $connection->fresh();
    }

    public function delete(Team $team, User $actor, LlmConnection $connection): void
    {
        $this->activity->record('connection.deleted', $connection, [
            'name' => $connection->name,
            'driver' => $connection->driver,
            'team_name' => $team->name,
        ], $actor, $team->id);

        $connection->delete();
    }
}

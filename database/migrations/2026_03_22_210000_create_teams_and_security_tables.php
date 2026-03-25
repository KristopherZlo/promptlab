<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_memberships')) {
            Schema::create('team_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 32)->default('editor')->index();
                $table->timestamps();

                $table->unique(['team_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('llm_connections')) {
            Schema::create('llm_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('driver', 32)->default('openai')->index();
                $table->string('base_url')->nullable();
                $table->text('api_key')->nullable();
                $table->json('models_json')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_default')->default(false)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 120)->index();
                $table->string('subject_type', 160)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_label')->nullable();
                $table->json('details_json')->nullable();
                $table->timestamps();

                $table->index(['team_id', 'created_at']);
                $table->index(['team_id', 'action']);
            });
        }

        $this->ensureCurrentTeamColumn();

        foreach ([
            'use_cases',
            'prompt_templates',
            'prompt_versions',
            'test_cases',
            'experiments',
            'experiment_runs',
            'evaluations',
            'library_entries',
        ] as $tableName) {
            $this->ensureTeamColumn($tableName);
        }

        $this->ensureUseCaseSlugIndex();

        $this->backfillDefaultWorkspace();
    }

    public function down(): void
    {
        foreach ([
            'library_entries',
            'evaluations',
            'experiment_runs',
            'experiments',
            'test_cases',
            'prompt_versions',
            'prompt_templates',
            'use_cases',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        Schema::table('use_cases', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_team_id');
        });

        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('llm_connections');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
    }

    private function backfillDefaultWorkspace(): void
    {
        if (DB::table('users')->count() === 0) {
            return;
        }

        if (
            DB::table('team_memberships')->count() > 0
            && DB::table('use_cases')->whereNotNull('team_id')->exists()
        ) {
            return;
        }

        $now = now();
        $baseSlug = 'evala-workspace';
        $slug = $baseSlug;
        $counter = 2;

        while (DB::table('teams')->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $teamId = DB::table('teams')->insertGetId([
            'name' => 'Evala Workspace',
            'slug' => Str::slug($slug),
            'description' => 'Default workspace migrated from the original single-team setup.',
            'created_by' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use ($teamId, $now): void {
                DB::table('team_memberships')->updateOrInsert(
                    [
                        'team_id' => $teamId,
                        'user_id' => $user->id,
                    ],
                    [
                        'role' => $user->role === 'admin' ? 'owner' : 'editor',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });

        DB::table('users')->whereNull('current_team_id')->update(['current_team_id' => $teamId]);

        foreach ([
            'use_cases',
            'prompt_templates',
            'prompt_versions',
            'test_cases',
            'experiments',
            'experiment_runs',
            'evaluations',
            'library_entries',
        ] as $tableName) {
            DB::table($tableName)->whereNull('team_id')->update(['team_id' => $teamId]);
        }
    }

    private function ensureCurrentTeamColumn(): void
    {
        if (! Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('current_team_id')->nullable()->after('role');
            });
        }

        if (! $this->hasIndex('users', 'users_current_team_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('current_team_id', 'users_current_team_id_index');
            });
        }

        if (! $this->hasForeignForColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('current_team_id', 'users_current_team_id_foreign')
                    ->references('id')
                    ->on('teams')
                    ->nullOnDelete();
            });
        }
    }

    private function ensureTeamColumn(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'team_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
            });
        }

        if (! $this->hasIndex($tableName, "{$tableName}_team_id_index")) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index('team_id', "{$tableName}_team_id_index");
            });
        }

        if (! $this->hasForeignForColumn($tableName, 'team_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreign('team_id', "{$tableName}_team_id_foreign")
                    ->references('id')
                    ->on('teams')
                    ->nullOnDelete();
            });
        }
    }

    private function ensureUseCaseSlugIndex(): void
    {
        if (! $this->hasIndex('use_cases', 'use_cases_team_slug_unique')) {
            if ($this->hasIndex('use_cases', 'use_cases_slug_unique')) {
                Schema::table('use_cases', function (Blueprint $table) {
                    $table->dropUnique('use_cases_slug_unique');
                });
            }

            Schema::table('use_cases', function (Blueprint $table) {
                $table->unique(['team_id', 'slug'], 'use_cases_team_slug_unique');
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$tableName}')"))
                ->contains(fn (object $row) => ($row->name ?? null) === $indexName);
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function hasForeignForColumn(string $tableName, string $columnName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA foreign_key_list('{$tableName}')"))
                ->contains(fn (object $row) => ($row->from ?? null) === $columnName);
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};

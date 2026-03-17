<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addUserIdentityColumns();
        $this->backfillUserIdentity();

        $this->addUseCaseOwnershipColumns();
        $this->backfillUseCaseOwnership();
    }

    public function down(): void
    {
        Schema::table('use_cases', function (Blueprint $table) {
            if (Schema::hasColumn('use_cases', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('use_cases', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }

    private function addUserIdentityColumns(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable();
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
        });
    }

    private function backfillUserIdentity(): void
    {
        DB::table('users')
            ->select(['id', 'name', 'first_name', 'last_name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $firstName = $user->first_name;
                $lastName = $user->last_name;

                if (filled($firstName) && filled($lastName)) {
                    return;
                }

                [$parsedFirst, $parsedLast] = $this->splitName((string) ($user->name ?? ''));

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $firstName ?: $parsedFirst ?: null,
                        'last_name' => $lastName ?: $parsedLast ?: null,
                    ]);
            });
    }

    private function addUseCaseOwnershipColumns(): void
    {
        Schema::table('use_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('use_cases', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('use_cases', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    private function backfillUseCaseOwnership(): void
    {
        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        DB::table('use_cases')
            ->select(['id', 'team_id', 'created_by', 'updated_by'])
            ->orderBy('id')
            ->get()
            ->each(function (object $useCase) use ($fallbackUserId): void {
                $teamCreatorId = null;

                if ($useCase->team_id) {
                    $teamCreatorId = DB::table('teams')
                        ->where('id', $useCase->team_id)
                        ->value('created_by');
                }

                $ownerId = $useCase->created_by ?: $teamCreatorId ?: $fallbackUserId;

                DB::table('use_cases')
                    ->where('id', $useCase->id)
                    ->update([
                        'created_by' => $ownerId,
                        'updated_by' => $useCase->updated_by ?: $ownerId,
                    ]);
            });
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return ['', ''];
        }

        $firstName = array_shift($parts) ?: '';
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }
};

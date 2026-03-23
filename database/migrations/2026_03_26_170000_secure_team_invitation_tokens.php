<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('team_invitations', 'token_ciphertext')) {
                $table->text('token_ciphertext')->nullable()->after('token');
            }
        });

        DB::table('team_invitations')
            ->select(['id', 'token', 'token_ciphertext'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $invitation): void {
                $token = (string) ($invitation->token ?? '');

                if ($token === '') {
                    return;
                }

                if ($invitation->token_ciphertext !== null && preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1) {
                    return;
                }

                DB::table('team_invitations')
                    ->where('id', $invitation->id)
                    ->update([
                        'token' => hash('sha256', $token),
                        'token_ciphertext' => Crypt::encryptString($token),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('team_invitations', 'token_ciphertext')) {
                $table->dropColumn('token_ciphertext');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('use_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 32)->index();
            $table->string('provider', 64)->nullable();
            $table->string('model_name');
            $table->decimal('temperature', 4, 2)->default(0.20);
            $table->unsignedInteger('max_tokens')->default(600);
            $table->json('prompt_version_ids_json')->nullable();
            $table->longText('input_text')->nullable();
            $table->json('variables_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('completed_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};

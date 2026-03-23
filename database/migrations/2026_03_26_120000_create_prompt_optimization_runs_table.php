<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('use_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_prompt_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->foreignId('derived_prompt_version_id')->nullable()->constrained('prompt_versions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_model_name');
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('budget_metric_calls')->default(18);
            $table->decimal('best_score', 8, 4)->nullable();
            $table->unsignedInteger('total_metric_calls')->nullable();
            $table->unsignedInteger('candidate_count')->nullable();
            $table->json('train_case_ids_json')->nullable();
            $table->json('validation_case_ids_json')->nullable();
            $table->json('config_json')->nullable();
            $table->json('seed_candidate_json')->nullable();
            $table->json('best_candidate_json')->nullable();
            $table->json('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['prompt_template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_optimization_runs');
    }
};

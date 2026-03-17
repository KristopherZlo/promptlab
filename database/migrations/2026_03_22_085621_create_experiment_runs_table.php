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
        Schema::create('experiment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('input_text');
            $table->json('variables_json')->nullable();
            $table->longText('compiled_prompt')->nullable();
            $table->longText('output_text')->nullable();
            $table->json('output_json')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('token_input')->nullable();
            $table->unsignedInteger('token_output')->nullable();
            $table->boolean('format_valid')->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->json('provider_response_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_runs');
    }
};

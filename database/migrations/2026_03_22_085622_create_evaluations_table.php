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
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('clarity_score')->nullable();
            $table->unsignedTinyInteger('correctness_score')->nullable();
            $table->unsignedTinyInteger('completeness_score')->nullable();
            $table->unsignedTinyInteger('tone_score')->nullable();
            $table->boolean('format_valid_manual')->nullable();
            $table->string('hallucination_risk', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['experiment_run_id', 'evaluator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};

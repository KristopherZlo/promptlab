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
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('use_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('input_text');
            $table->text('expected_output')->nullable();
            $table->json('expected_json')->nullable();
            $table->json('variables_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};

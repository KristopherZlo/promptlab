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
        Schema::create('library_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->string('recommended_model')->nullable();
            $table->string('best_for')->nullable();
            $table->text('usage_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_entries');
    }
};

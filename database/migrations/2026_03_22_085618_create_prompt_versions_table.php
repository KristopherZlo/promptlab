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
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_template_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->string('change_summary')->nullable();
            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt_template');
            $table->json('variables_schema')->nullable();
            $table->string('output_type', 32)->default('text');
            $table->json('output_schema_json')->nullable();
            $table->text('notes')->nullable();
            $table->string('preferred_model')->nullable();
            $table->boolean('is_library_approved')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['prompt_template_id', 'version_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_versions');
    }
};

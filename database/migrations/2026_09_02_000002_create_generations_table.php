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
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('prompt_text');
            $table->text('erd_mermaid_text')->nullable();
            $table->jsonb('migration_files')->nullable();
            $table->string('status')->default('draft'); // draft | injected
            $table->string('target_version')->default('13');
            $table->string('ai_driver')->default('openrouter'); // openrouter | openai | ollama
            $table->string('database_dialect')->default('mysql'); // mysql | pgsql | sqlite | sqlsrv
            $table->string('target_framework')->default('laravel'); // laravel | express_prisma | express_drizzle | springboot_hibernate | raw_sql
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};

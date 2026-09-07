<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-1001: Fondasi fitur Chatbot Dokumen (URD → PRD → SRS → System Design).
 * - doc_projects: satu baris per ide proyek.
 * - doc_messages: riwayat percakapan (Human-in-the-Loop), pola job_status ala generations.
 * - doc_versions: snapshot Markdown immutable per tahap (pola dry-run).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('target_framework', 40)->nullable();
            $table->string('stage', 20)->default('brief')->index();
            $table->string('ai_model', 100)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('doc_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_project_id')->constrained('doc_projects')->cascadeOnDelete();
            $table->string('role', 20); // user | assistant | system
            $table->text('content');
            $table->string('stage', 20)->default('brief');
            $table->string('ai_model', 100)->nullable();
            $table->string('job_status', 20)->default('ready')->index();
            $table->text('job_error')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            $table->index(['doc_project_id', 'created_at']);
        });

        Schema::create('doc_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_project_id')->constrained('doc_projects')->cascadeOnDelete();
            $table->string('doc_type', 20)->index(); // urd | prd | srs | sysdesign
            $table->unsignedInteger('version')->default(1);
            $table->text('content_markdown');
            $table->string('status', 20)->default('draft')->index(); // draft | approved | rejected
            $table->foreignUuid('parent_version_id')->nullable()->constrained('doc_versions')->nullOnDelete();
            $table->string('ai_model', 100)->nullable();
            $table->timestamps();

            $table->unique(['doc_project_id', 'doc_type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_versions');
        Schema::dropIfExists('doc_messages');
        Schema::dropIfExists('doc_projects');
    }
};

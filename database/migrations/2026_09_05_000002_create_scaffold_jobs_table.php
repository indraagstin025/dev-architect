<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-606: Antrean pembuatan proyek baru dari terminal (scaffold).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scaffold_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // laravel | express_prisma | express_drizzle | springboot_hibernate | raw_sql
            $table->string('template', 30);
            $table->string('project_name', 120);
            $table->text('parent_path');
            $table->text('target_path');
            $table->json('options')->nullable();
            // queued | processing | ready | failed | cancelled
            $table->string('status', 20)->default('queued')->index();
            $table->text('log')->nullable();
            $table->text('error')->nullable();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scaffold_jobs');
    }
};

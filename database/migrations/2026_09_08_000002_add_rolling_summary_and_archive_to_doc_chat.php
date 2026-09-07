<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TASK-M2-07 & TASK-M2-08:
     * Dukungan rolling context summary (Three-Tier Memory) dan pengarsipan sesi obrolan.
     */
    public function up(): void
    {
        Schema::table('doc_projects', function (Blueprint $table) {
            $table->text('context_summary')->nullable()->after('stage');
        });

        Schema::table('doc_messages', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->index()->after('job_status');
        });
    }

    public function down(): void
    {
        Schema::table('doc_messages', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });

        Schema::table('doc_projects', function (Blueprint $table) {
            $table->dropColumn('context_summary');
        });
    }
};

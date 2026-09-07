<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TASK-M2-01 & TASK-M2-02:
     * Dukungan status draft/virtual untuk proyek yang baru berupa ide dokumen
     * tanpa folder fisik di disk komputer.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('absolute_path', 500)->nullable()->change();
            $table->boolean('is_draft')->default(false)->index()->after('absolute_path');
            $table->foreignUuid('doc_project_id')->nullable()->after('database_dialect')->constrained('doc_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doc_project_id');
            $table->dropColumn('is_draft');
            $table->string('absolute_path', 500)->nullable(false)->change();
        });
    }
};

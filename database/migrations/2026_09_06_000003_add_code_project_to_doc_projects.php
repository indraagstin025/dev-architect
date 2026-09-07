<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-1004: Tautkan proyek dokumen ke proyek kode (Q16 konteks existing,
 * Q10 handoff 1:1). Nullable agar dokumen bisa dibuat sebelum proyek ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doc_projects', function (Blueprint $table) {
            $table->foreignUuid('code_project_id')->nullable()->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doc_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('code_project_id');
        });
    }
};

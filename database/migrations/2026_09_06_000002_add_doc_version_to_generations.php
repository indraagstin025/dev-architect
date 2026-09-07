<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan tambahan B1: relasi handoff System Design → Generator Skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignUuid('doc_version_id')->nullable()->constrained('doc_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doc_version_id');
        });
    }
};

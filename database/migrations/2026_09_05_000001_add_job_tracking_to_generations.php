<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-705: Pelacakan status antrean AI pada tabel generations.
 *
 * Alur: queued -> processing -> ready | failed | cancelled
 * - job_error: pesan kegagalan (atau ringkasan error linter).
 * - job_warnings: peringatan linter saat hasil lolos validasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->string('job_status', 20)->default('queued')->index();
            $table->text('job_error')->nullable();
            $table->json('job_warnings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn(['job_status', 'job_error', 'job_warnings']);
        });
    }
};

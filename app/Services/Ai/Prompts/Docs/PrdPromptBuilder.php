<?php

namespace App\Services\Ai\Prompts\Docs;

class PrdPromptBuilder extends DocPromptBase
{
    public static function docType(): string
    {
        return 'prd';
    }

    public static function sections(): array
    {
        return ['1. Ringkasan Produk', '2. Tujuan & Metrik Keberhasilan', '3. Persona & User Journey', '4. Fitur (P0/P1/P2)', '5. Non-Goals', '6. Kriteria Penerimaan'];
    }

    public static function build(array $ctx): string
    {
        $section = $ctx['section'] ?? null;

        return self::header($ctx, 'Susun Product Requirement Document (PRD) berdasarkan URD yang sudah di-approve.') . "\n\n"
            . 'STRUKTUR PRD:' . "\n"
            . '- 1. Ringkasan Produk (2-3 kalimat nilai utama)\n'
            . '- 2. Tujuan & Metrik Keberhasilan (target terukur, tanpa estimasi waktu pengerjaan)' . "\n"
            . '- 3. Persona & User Journey (alur langkah per persona)' . "\n"
            . '- 4. Fitur berprioritas P0 (wajib) / P1 (penting) / P2 (nanti), tiap fitur: deskripsi + dependensi fitur lain' . "\n"
            . '- 5. Non-Goals (yang eksplisit TIDAK dikerjakan)' . "\n"
            . '- 6. Kriteria Penerimaan per fitur P0 (Given-When-Then)' . "\n\n"
            . 'Setiap fitur HARUS tertelusur ke minimal satu kebutuhan URD (tulis kode UR-XX di sampingnya).' . "\n\n"
            . self::sectionRule($section, self::sections());
    }
}

<?php

namespace App\Services\Ai\Prompts\Docs;

class SrsPromptBuilder extends DocPromptBase
{
    public static function docType(): string
    {
        return 'srs';
    }

    public static function sections(): array
    {
        return ['1. Pendahuluan Teknis', '2. Kebutuhan Fungsional (FR)', '3. Kebutuhan Non-Fungsional (NFR)', '4. Model Data', '5. Antarmuka & Integrasi', '6. Kriteria Uji'];
    }

    public static function build(array $ctx): string
    {
        $section = $ctx['section'] ?? null;

        return self::header($ctx, 'Susun Software Requirement Specification (SRS) berdasarkan PRD yang sudah di-approve.') . "\n\n"
            . 'STRUKTUR SRS:' . "\n"
            . '- 1. Pendahuluan Teknis (tujuan + definisi istilah/glosarium)' . "\n"
            . '- 2. Kebutuhan Fungsional bernomor FR-01, FR-02, ... (tiap butir: deskripsi, aktor, prasyarat, tertelusur ke fitur PRD)' . "\n"
            . '- 3. Kebutuhan Non-Fungsional bernomor NFR-01, ... (kinerja terukur, keamanan, ketersediaan, skalabilitas)' . "\n"
            . '- 4. Model Data (entitas + atribut kunci + relasi tingkat konseptual, tanpa DDL)' . "\n"
            . '- 5. Antarmuka & Integrasi (endpoint/API antar modul, format data)' . "\n"
            . '- 6. Kriteria Uji per FR penting (skenario uji singkat)' . "\n\n"
            . self::sectionRule($section, self::sections());
    }
}

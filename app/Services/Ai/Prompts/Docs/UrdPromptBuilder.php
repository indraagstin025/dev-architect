<?php

namespace App\Services\Ai\Prompts\Docs;

class UrdPromptBuilder extends DocPromptBase
{
    public static function docType(): string
    {
        return 'urd';
    }

    public static function sections(): array
    {
        return ['1. Pendahuluan', '2. Ruang Lingkup', '3. Target Pengguna', '4. Kebutuhan Fungsional', '5. Kebutuhan Non-Fungsional', '6. Batasan'];
    }

    public static function build(array $ctx): string
    {
        $section = $ctx['section'] ?? null;

        return self::header($ctx, 'Susun User Requirement Document (URD).') . "\n\n"
            . 'STRUKTUR URD:' . "\n"
            . '- 1. Pendahuluan (tujuan dokumen)\n'
            . '- 2. Ruang Lingkup (fitur termasuk & eksplisit tidak termasuk)\n'
            . '- 3. Target Pengguna (persona + kebutuhan tiap persona)\n'
            . '- 4. Kebutuhan Fungsional (daftar UR-01, UR-02, ... 1 kalimat tiap butir, terukur)\n'
            . '- 5. Kebutuhan Non-Fungsional (kinerja, keamanan, ketersediaan)\n'
            . '- 6. Batasan (teknis & bisnis)' . "\n\n"
            . self::sectionRule($section, self::sections());
    }
}

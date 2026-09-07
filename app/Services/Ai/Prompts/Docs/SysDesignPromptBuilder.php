<?php

namespace App\Services\Ai\Prompts\Docs;

class SysDesignPromptBuilder extends DocPromptBase
{
    public static function docType(): string
    {
        return 'sysdesign';
    }

    public static function sections(): array
    {
        return ['1. Gambaran Arsitektur', '2. Modul & Tanggung Jawab', '3. Desain API', '4. Usulan Skema Database', '5. Risiko Teknis', '6. Asumsi'];
    }

    public static function build(array $ctx): string
    {
        $section = $ctx['section'] ?? null;

        return self::header($ctx, 'Susun System Design berdasarkan SRS yang sudah di-approve.') . "\n\n"
            . 'STRUKTUR SYSTEM DESIGN:' . "\n"
            . '- 1. Gambaran Arsitektur (diagram Mermaid: graph TD untuk komponen + alur data, lalu penjelasan 1 paragraf)' . "\n"
            . '- 2. Modul & Tanggung Jawab (tabel: Modul | Tanggung Jawab | Dependensi | Complexity Low/Med/High + alasan)' . "\n"
            . '- 3. Desain API (tabel: Method | Endpoint | Deskripsi | Auth)' . "\n"
            . '- 4. Usulan Skema Database (daftar tabel + kolom kunci + relasi + index yang disarankan; TULIS SEBAGAI DESAIN, bukan DDL final — DDL dibuat di tahap Generator Skema)' . "\n"
            . '- 5. Risiko Teknis' . "\n"
            . '- 6. Asumsi (maksimal 5)' . "\n\n"
            . self::riskTableGuide() . "\n\n"
            . self::sectionRule($section, self::sections());
    }
}

<?php 

namespace App\Contracts;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;

interface AIDriverInterface
{
    /**
     * Men-generate skema database dari prompt pengguna.
     *
     * @return array{
     *     erd_mermaid_text: string,
     *     migration_files: array<array{filename: string, content: string}>
     * }
     */
    public function generate(
        string $prompt, 
        TargetFramework $framework = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL, 
        string $targetVersion = '13',
        ?string $model = null
    ): array;

    /**
     * Chat percakapan terstruktur (untuk asisten dokumen atau instruksi interaktif).
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array;
}
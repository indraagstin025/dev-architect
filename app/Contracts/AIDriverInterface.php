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
        string $targetVersion = '13'
    ): array;
}
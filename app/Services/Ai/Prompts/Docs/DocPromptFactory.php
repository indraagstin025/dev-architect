<?php

namespace App\Services\Ai\Prompts\Docs;

use InvalidArgumentException;

class DocPromptFactory
{
    /** @var array<string, class-string<DocPromptBase>> */
    protected static array $map = [
        'urd' => UrdPromptBuilder::class,
        'prd' => PrdPromptBuilder::class,
        'srs' => SrsPromptBuilder::class,
        'sysdesign' => SysDesignPromptBuilder::class,
    ];

    /**
     * @return class-string<DocPromptBase>
     */
    public static function for(string $docType): string
    {
        if (! isset(self::$map[$docType])) {
            throw new InvalidArgumentException("Tipe dokumen [{$docType}] tidak didukung.");
        }

        return self::$map[$docType];
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::$map);
    }
}

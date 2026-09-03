<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case DRAFT = 'draft';
    case INJECTED = 'injected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft (Dry-Run)',
            self::INJECTED => 'Injected to Project',
        };
    }
}

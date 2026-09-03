<?php

namespace App\Services\Ai;

use App\Contracts\AIDriverInterface;
use App\Models\AppSetting;
use App\Services\Ai\Drivers\OpenRouterDriver;
use InvalidArgumentException;

class AiManager
{
    /**
     * Mendapatkan instance driver AI yang sedang aktif.
     */
    public function driver(?string $name = null): AIDriverInterface
    {
        $driverName = $name ?? AppSetting::get('active_ai_driver', 'openrouter');

        return match (strtolower($driverName)) {
            'openrouter' => new OpenRouterDriver(),
            default => throw new InvalidArgumentException("Driver AI '{$driverName}' tidak didukung."),
        };
    }
}

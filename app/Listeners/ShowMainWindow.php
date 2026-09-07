<?php

namespace App\Listeners;

use App\Events\ToggleWindowVisibility;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Window;

class ShowMainWindow
{
    /**
     * Handle the event.
     */
    public function handle(ToggleWindowVisibility $event): void
    {
        try {
            Window::open('main');
        } catch (\Throwable $e) {
            Log::warning("Gagal memfokuskan jendela aplikasi desktop: " . $e->getMessage());
        }
    }
}

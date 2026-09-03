<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Facades\Window;

class ToggleWindowVisibility
{
    use Dispatchable, SerializesModels;

    public function __construct()
    {
        // Buka dan fokuskan jendela utama aplikasi saat hotkey ditekan
        Window::open('main');
    }
}

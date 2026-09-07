<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToggleWindowVisibility
{
    use Dispatchable, SerializesModels;

    public function __construct()
    {
        // Event data carrier murni
    }
}

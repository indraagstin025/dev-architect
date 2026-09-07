<?php

namespace App\Services;

use RuntimeException;

/**
 * Sinyal pembatalan scaffold oleh pengguna (bukan kegagalan).
 */
class ScaffoldCancelledException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Shared;

use RuntimeException;

/**
 * The thing the caller asked for does not exist, or is not theirs to know about (reported as 404).
 */
class NotFound extends RuntimeException
{
}

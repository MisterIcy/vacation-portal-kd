<?php

declare(strict_types=1);

namespace App\Shared;

use RuntimeException;

/**
 * The request is well formed but clashes with the current state of things, such as a value that is
 * already taken, a request that was already decided, or dates that are no longer free (reported as 409).
 */
class Conflict extends RuntimeException
{
}

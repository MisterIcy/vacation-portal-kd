<?php

declare(strict_types=1);

namespace App\Shared;

use RuntimeException;

/**
 * The caller is known but their role does not allow the operation (reported as 403).
 */
final class AccessDenied extends RuntimeException
{
    public static function forRole(): self
    {
        return new self('Forbidden');
    }
}

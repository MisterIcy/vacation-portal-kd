<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Exceptions;

use App\Shared\Conflict;

/**
 * The requested days collide with another request of the same employee that is still
 * pending or already approved (reported as 409).
 */
final class VacationOverlap extends Conflict
{
    public static function withExistingRequest(): self
    {
        return new self('The requested dates overlap with another pending or approved vacation request.');
    }
}

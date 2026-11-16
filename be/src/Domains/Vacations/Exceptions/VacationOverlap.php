<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Exceptions;

use InvalidArgumentException;

/**
 * The requested days collide with another request of the same employee that is still
 * pending or already approved.
 *
 * It extends InvalidArgumentException so it is reported as a 400 until the API
 * distinguishes conflicts (409) from other invalid requests.
 */
final class VacationOverlap extends InvalidArgumentException
{
    public static function withExistingRequest(): self
    {
        return new self('The requested dates overlap with another pending or approved vacation request.');
    }
}

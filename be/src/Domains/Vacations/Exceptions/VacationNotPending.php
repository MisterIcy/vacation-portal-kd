<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Exceptions;

use InvalidArgumentException;

/**
 * A change was attempted on a vacation request that has already been decided.
 *
 * It extends InvalidArgumentException so it is reported to the client as a 400 until
 * the API distinguishes conflicts (409) from other invalid requests.
 */
final class VacationNotPending extends InvalidArgumentException
{
    public static function forUpdate(): self
    {
        return new self('Only pending vacations can be updated.');
    }

    public static function forDeletion(): self
    {
        return new self('Only pending vacations can be deleted.');
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Exceptions;

use App\Shared\Conflict;

/**
 * A change was attempted on a vacation request that has already been decided (reported as 409).
 */
final class VacationNotPending extends Conflict
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

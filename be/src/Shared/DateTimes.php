<?php

declare(strict_types=1);

namespace App\Shared;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Date handling rules for the whole application:
 *
 * - Instants (created_at, updated_at) are stored and read as UTC, and sent to
 *   clients as ISO-8601 with an explicit offset.
 * - Calendar dates (a vacation's first and last day) have no time zone. They are
 *   sent as plain "Y-m-d" strings.
 */
final class DateTimes
{
    /**
     * @throws DateMalformedStringException
     */
    public static function fromDatabase(string $value): DateTime
    {
        return new DateTime($value, new DateTimeZone('UTC'));
    }

    public static function toIso(?DateTimeInterface $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }
}

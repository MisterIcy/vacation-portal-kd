<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Models;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The first and last day of a vacation, both included.
 *
 * Only the calendar day matters (a vacation has no time of day and no time zone), so
 * any time part is dropped. The range owns the rules about its own shape: the first
 * day is not after the last, and it spans at most MAX_DAYS days.
 */
final readonly class DateRange
{
    public const int MAX_DAYS = 60;

    private DateTimeImmutable $from;
    private DateTimeImmutable $to;

    /**
     * @throws InvalidArgumentException when the range is reversed or longer than MAX_DAYS
     */
    public function __construct(DateTimeInterface $from, DateTimeInterface $to)
    {
        $this->from = self::calendarDay($from);
        $this->to = self::calendarDay($to);

        if ($this->from > $this->to) {
            throw new InvalidArgumentException('The "from" date must be earlier than the "to" date.');
        }

        if ($this->days() > self::MAX_DAYS) {
            throw new InvalidArgumentException('Vacation duration cannot be more than ' . self::MAX_DAYS . ' days.');
        }
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    /**
     * Total days, counting both the first and the last day (one day for a single-day range).
     */
    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /**
     * Whether the two ranges share at least one day. Ranges that merely touch, the last
     * day of one being the first day of the other, share that day and so overlap.
     */
    public function overlaps(self $other): bool
    {
        return $this->from <= $other->to && $other->from <= $this->to;
    }

    public function startsBefore(DateTimeInterface $day): bool
    {
        return $this->from < self::calendarDay($day);
    }

    private static function calendarDay(DateTimeInterface $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value->format('Y-m-d'), new DateTimeZone('UTC'));
    }
}

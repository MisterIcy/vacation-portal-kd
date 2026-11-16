<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Models;

use App\Domains\Vacations\Models\DateRange;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    #[DataProvider('dayCounts')]
    public function testDaysCountsBothEndsInclusively(string $from, string $to, int $expectedDays): void
    {
        $this->assertSame($expectedDays, $this->range($from, $to)->days());
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function dayCounts(): array
    {
        return [
            'a single day' => ['2026-07-01', '2026-07-01', 1],
            'two consecutive days' => ['2026-07-01', '2026-07-02', 2],
            'across a month boundary' => ['2026-01-30', '2026-02-02', 4],
            'across a year boundary' => ['2026-12-30', '2027-01-02', 4],
            'across a leap day' => ['2028-02-28', '2028-03-01', 3],
            'the longest allowed range' => ['2026-01-01', '2026-03-01', 60],
        ];
    }

    public function testRejectsARangeLongerThanTheMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vacation duration cannot be more than 60 days.');

        $this->range('2026-01-01', '2026-03-02'); // 61 days
    }

    public function testRejectsAReversedRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "from" date must be earlier than the "to" date.');

        $this->range('2026-07-10', '2026-07-09');
    }

    public function testOnlyTheCalendarDayMatters(): void
    {
        $range = new DateRange(new DateTime('2026-07-01 23:59:59'), new DateTime('2026-07-01 00:00:01'));

        $this->assertSame(1, $range->days(), 'a later time on the same day is still a single day');
        $this->assertSame('2026-07-01 00:00:00', $range->from()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $range->from()->getTimezone()->getName());
    }

    #[DataProvider('overlappingPairs')]
    public function testOverlaps(string $firstFrom, string $firstTo, string $secondFrom, string $secondTo, bool $expected): void
    {
        $first = $this->range($firstFrom, $firstTo);
        $second = $this->range($secondFrom, $secondTo);

        $this->assertSame($expected, $first->overlaps($second));
        $this->assertSame($expected, $second->overlaps($first), 'overlap is symmetric');
    }

    /**
     * @return array<string, array{string, string, string, string, bool}>
     */
    public static function overlappingPairs(): array
    {
        return [
            'identical ranges' => ['2026-07-01', '2026-07-05', '2026-07-01', '2026-07-05', true],
            'one inside the other' => ['2026-07-01', '2026-07-10', '2026-07-03', '2026-07-04', true],
            'partial overlap' => ['2026-07-01', '2026-07-05', '2026-07-04', '2026-07-09', true],
            'sharing only the boundary day' => ['2026-07-01', '2026-07-05', '2026-07-05', '2026-07-09', true],
            'adjacent, no shared day' => ['2026-07-01', '2026-07-05', '2026-07-06', '2026-07-09', false],
            'far apart' => ['2026-07-01', '2026-07-05', '2026-09-01', '2026-09-05', false],
            'single days that match' => ['2026-07-01', '2026-07-01', '2026-07-01', '2026-07-01', true],
            'single days that differ' => ['2026-07-01', '2026-07-01', '2026-07-02', '2026-07-02', false],
        ];
    }

    public function testStartsBefore(): void
    {
        $range = $this->range('2026-07-10', '2026-07-12');

        $this->assertTrue($range->startsBefore(new DateTime('2026-07-11')));
        $this->assertFalse($range->startsBefore(new DateTime('2026-07-10')), 'starting today is not in the past');
        $this->assertFalse($range->startsBefore(new DateTime('2026-07-09')));
        $this->assertFalse($range->startsBefore(new DateTime('2026-07-10 23:59')), 'the time of day is ignored');
    }

    private function range(string $from, string $to): DateRange
    {
        return new DateRange(new DateTime($from), new DateTime($to));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\DateTimes;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class DateTimesTest extends TestCase
{
    public function testDatabaseValuesAreReadAsUtcRegardlessOfPhpDefault(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');

        try {
            $value = DateTimes::fromDatabase('2026-01-15 10:00:00');
        } finally {
            date_default_timezone_set($original);
        }

        $this->assertSame('UTC', $value->getTimezone()->getName());
        $this->assertSame('2026-01-15T10:00:00+00:00', DateTimes::toIso($value));
    }

    public function testIsoOutputCarriesTheOffset(): void
    {
        $value = new DateTime('2026-07-01 12:30:00', new DateTimeZone('Europe/Athens'));

        $this->assertSame('2026-07-01T12:30:00+03:00', DateTimes::toIso($value));
    }

    public function testNullStaysNull(): void
    {
        $this->assertNull(DateTimes::toIso(null));
    }
}

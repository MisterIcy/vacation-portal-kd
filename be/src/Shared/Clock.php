<?php

declare(strict_types=1);

namespace App\Shared;

use DateTimeImmutable;

/**
 * The current date, behind an interface so rules that depend on "today" can be tested
 * without waiting for a particular day.
 */
interface Clock
{
    /**
     * The current calendar day (UTC), at midnight.
     */
    public function today(): DateTimeImmutable;
}

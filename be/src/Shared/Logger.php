<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Where the application reports things that went wrong but did not stop a request. A port, so
 * code that must not fail its caller (notifications) can be tested for what it reports.
 *
 * Messages must not contain personal data such as email addresses.
 */
interface Logger
{
    public function warning(string $message): void;

    public function error(string $message): void;
}

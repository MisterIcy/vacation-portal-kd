<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Writes to PHP's error log, which is stderr in the container. The same place the error
 * middleware and the start-up checks already report to.
 */
final class ErrorLogLogger implements Logger
{
    public function warning(string $message): void
    {
        $this->write('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        error_log(sprintf('%s %s %s', date(DATE_ATOM), $level, $message));
    }
}

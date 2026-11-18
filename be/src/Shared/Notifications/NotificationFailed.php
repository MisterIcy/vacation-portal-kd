<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use RuntimeException;
use Throwable;

/**
 * A notification could not be delivered. It says whether trying again can help: a full disk or
 * an unreachable server may recover, an address that is not valid never will.
 */
final class NotificationFailed extends RuntimeException
{
    private function __construct(string $message, private readonly bool $transient, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}

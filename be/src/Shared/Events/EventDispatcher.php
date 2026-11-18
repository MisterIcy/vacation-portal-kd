<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Lets one part of the application announce that something happened without knowing who
 * reacts to it. The announcer is never told that a reaction failed.
 */
interface EventDispatcher
{
    public function dispatch(object $event): void;
}

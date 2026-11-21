<?php

declare(strict_types=1);

namespace App\Shared\Events;

use App\Shared\Logger;
use Closure;
use Throwable;

/**
 * Runs the listeners registered for an event, in registration order, inside the request.
 *
 * A listener that throws is logged and skipped: by the time an event is dispatched the thing it
 * announces has already happened and been stored, so a failing reaction (an unreachable mail
 * server, a full disk) must neither undo it nor turn the caller's success into an error, and it
 * must not stop the other listeners.
 *
 * Moving to a queue later means replacing this class, not the events or their listeners.
 */
final class SynchronousEventDispatcher implements EventDispatcher
{
    /** @var array<class-string, list<Closure(object): void>> */
    private array $listeners = [];

    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $eventClass
     * @param Closure(T): void $listener
     */
    public function listen(string $eventClass, Closure $listener): void
    {
        /** @var Closure(object): void $listener */
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners as $eventClass => $listeners) {
            if (!$event instanceof $eventClass) {
                continue;
            }

            foreach ($listeners as $listener) {
                try {
                    $listener($event);
                } catch (Throwable $e) {
                    // The message names the failure, not the event's data: that can be personal.
                    $this->logger->error(sprintf(
                        'A listener of %s failed and was skipped: %s: %s',
                        $event::class,
                        $e::class,
                        $e->getMessage(),
                    ));
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Shared\Logger;
use Closure;
use InvalidArgumentException;

/**
 * Tries again when a notification fails for a reason that can pass (a busy disk, a brief
 * outage), waiting a little longer each time, and gives up after a fixed number of attempts.
 * A failure that cannot pass is not retried. Every failed attempt is logged.
 *
 * It runs inside the request that decided the vacation, so the waits are kept short; with a
 * real provider this belongs in a background worker reading an outbox (see SYSTEM_DESIGN.md).
 */
final readonly class RetryingNotificationService implements NotificationService
{
    /** @var Closure(int): void */
    private Closure $sleepMilliseconds;

    /**
     * @param (Closure(int): void)|null $sleepMilliseconds replaced in tests so they do not wait
     */
    public function __construct(
        private NotificationService $inner,
        private Logger $logger,
        private int $maxAttempts = 3,
        private int $initialDelayMilliseconds = 100,
        ?Closure $sleepMilliseconds = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('At least one attempt is needed.');
        }

        $this->sleepMilliseconds = $sleepMilliseconds ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public function send(VacationDecisionNotification $notification): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $this->inner->send($notification);

                return;
            } catch (NotificationFailed $e) {
                $giveUp = !$e->isTransient() || $attempt >= $this->maxAttempts;

                $this->logger->warning(sprintf(
                    'Notification of the %s decision on vacation %d failed (attempt %d of %d, %s): %s',
                    $notification->decision->value,
                    $notification->vacationId,
                    $attempt,
                    $this->maxAttempts,
                    $giveUp ? 'giving up' : 'will retry',
                    $e->getMessage(),
                ));

                if ($giveUp) {
                    throw $e;
                }

                ($this->sleepMilliseconds)($this->initialDelayMilliseconds * 2 ** ($attempt - 1));
            }
        }
    }
}

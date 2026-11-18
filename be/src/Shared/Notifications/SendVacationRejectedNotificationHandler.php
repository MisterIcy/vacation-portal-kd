<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Shared\Events\VacationRejectedEvent;

/**
 * Reacts to a rejection by notifying the employee through the configured service.
 */
final readonly class SendVacationRejectedNotificationHandler
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * @throws NotificationFailed
     */
    public function __invoke(VacationRejectedEvent $event): void
    {
        $this->notifications->send($event->notification);
    }
}

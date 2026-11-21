<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Shared\Events\VacationApprovedEvent;

/**
 * Reacts to an approval by notifying the employee through the configured service.
 */
final readonly class SendVacationApprovedNotificationHandler
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * @throws NotificationFailed
     */
    public function __invoke(VacationApprovedEvent $event): void
    {
        $this->notifications->send($event->notification);
    }
}

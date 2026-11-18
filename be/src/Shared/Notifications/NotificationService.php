<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

/**
 * Tells an employee that their vacation request was decided, over some channel.
 */
interface NotificationService
{
    /**
     * @throws NotificationFailed when it could not be delivered
     */
    public function send(VacationDecisionNotification $notification): void;
}

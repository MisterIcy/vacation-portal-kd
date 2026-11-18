<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

/**
 * Gets an email out of the application. The only implementation writes files; an SMTP or
 * provider-API one would replace it without touching what the email says.
 */
interface EmailTransport
{
    /**
     * @throws NotificationFailed
     */
    public function deliver(EmailMessage $message): void;
}

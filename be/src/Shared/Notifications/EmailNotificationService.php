<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

/**
 * Notifies by email: decides what the email says and leaves getting it out to the transport.
 */
final readonly class EmailNotificationService implements NotificationService
{
    public function __construct(private EmailTransport $transport)
    {
    }

    public function send(VacationDecisionNotification $notification): void
    {
        if (filter_var($notification->recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            // Retrying cannot fix the address.
            throw NotificationFailed::permanent("The address of user {$notification->recipientId} is not a valid email address.");
        }

        $this->transport->deliver(new EmailMessage(
            $notification->recipientEmail,
            $notification->recipientName,
            "Your vacation request was {$notification->decision->value}",
            $this->body($notification),
        ));
    }

    private function body(VacationDecisionNotification $notification): string
    {
        $lines = [
            "Hello {$notification->recipientName},",
            '',
            sprintf(
                'Your vacation request #%d from %s to %s (%d %s) has been %s.',
                $notification->vacationId,
                $notification->from->format('Y-m-d'),
                $notification->to->format('Y-m-d'),
                $notification->days,
                $notification->days === 1 ? 'day' : 'days',
                $notification->decision->value,
            ),
            '',
            "Your reason: {$notification->reason}",
        ];

        if ($notification->message !== null) {
            $lines[] = "Message from your manager: {$notification->message}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }
}

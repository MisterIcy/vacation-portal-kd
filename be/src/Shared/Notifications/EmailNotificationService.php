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
            "Hello {$this->oneLine($notification->recipientName)},",
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
            "Your reason: {$this->quoted($notification->reason)}",
        ];

        if ($notification->message !== null) {
            $lines[] = "Message from your manager: {$this->quoted($notification->message)}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Text a person wrote, as one line: a stored name may still contain line breaks from before they
     * were refused at input.
     */
    private function oneLine(string $text): string
    {
        return trim((string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $text));
    }

    /**
     * Text a person wrote, which may run over several lines. The lines after the first are indented, so
     * that none of them can start with "Message from your manager:" or any other label of this email and
     * pass for something the system said. Control characters other than the line breaks are dropped.
     */
    private function quoted(string $text): string
    {
        $lines = preg_split('/\R/u', trim($text));
        if ($lines === false) {
            $lines = preg_split('/\R/', trim($text)) ?: [];
        }

        $lines = array_map(static fn (string $line): string => (string) preg_replace('/[^\P{Cc}\t]/u', '', $line), $lines);
        $first = array_shift($lines) ?? '';

        return implode("\r\n", [$first, ...array_map(static fn (string $line): string => $line === '' ? '' : '    ' . $line, $lines)]);
    }
}

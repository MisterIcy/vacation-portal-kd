<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use Random\RandomException;

/**
 * "Sends" an email by writing it to its own file in a directory, so no mail provider is needed
 * to see what would have been sent. One file per email, named from the time and a random part
 * (never from anything a user typed), created exclusively so no file is ever overwritten.
 *
 * Files are readable by the owner and group only: they contain personal data.
 */
final readonly class FileEmailTransport implements EmailTransport
{
    public function __construct(private string $directory, private string $from)
    {
    }

    public function deliver(EmailMessage $message): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $content = implode("\r\n", [
            'Date: ' . $now->format(DATE_RFC2822),
            "From: {$this->from}",
            sprintf('To: %s <%s>', $message->toName, $message->to),
            "Subject: {$message->subject}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $message->body,
        ]);

        try {
            $name = $now->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        } catch (RandomException $e) {
            throw NotificationFailed::transient('Could not name the notification file.', $e);
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw NotificationFailed::transient("Could not create the notifications directory '{$this->directory}'.");
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $name;

        $file = @fopen($path, 'xb');
        if ($file === false) {
            throw NotificationFailed::transient("Could not create a notification file in '{$this->directory}'.");
        }

        $written = @fwrite($file, $content);
        fclose($file);

        if ($written !== strlen($content)) {
            @unlink($path);
            throw NotificationFailed::transient("Could not write a notification file in '{$this->directory}'.");
        }

        @chmod($path, 0640);
    }
}

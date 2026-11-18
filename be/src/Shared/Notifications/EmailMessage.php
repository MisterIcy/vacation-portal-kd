<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

/**
 * An email as the transport receives it. Headers cannot carry line breaks (that is how a
 * header injection adds recipients), so any in the values are removed here, once.
 */
final readonly class EmailMessage
{
    public string $toName;
    public string $subject;

    public function __construct(
        public string $to,
        string $toName,
        string $subject,
        public string $body,
    ) {
        $this->toName = self::singleLine($toName);
        $this->subject = self::singleLine($subject);
    }

    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\0]+/', ' ', $value));
    }
}

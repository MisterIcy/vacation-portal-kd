<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Everything needed to tell an employee that their vacation request was decided: who to tell,
 * which request, what was decided and, optionally, what the manager wrote.
 *
 * Plain values only, so it depends on neither the vacation nor the user model and can travel
 * in an event to any notification channel.
 */
final readonly class VacationDecisionNotification
{
    public const int MAX_MESSAGE_LENGTH = 500;

    public ?string $message;

    /**
     * @throws InvalidArgumentException when the message is too long
     */
    public function __construct(
        public int $recipientId,
        public string $recipientName,
        public string $recipientEmail,
        public int $vacationId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public int $days,
        public string $reason,
        public VacationDecision $decision,
        ?string $message = null,
    ) {
        $message = $message === null ? null : trim($message);

        if ($message !== null && mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException("'message' must be at most " . self::MAX_MESSAGE_LENGTH . ' characters.');
        }

        $this->message = $message === '' ? null : $message;
    }
}

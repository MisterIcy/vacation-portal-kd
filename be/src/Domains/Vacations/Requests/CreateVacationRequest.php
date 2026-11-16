<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Requests;

use App\Shared\RequestBody;
use DateTime;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The input for submitting a vacation request, validated for shape and format.
 * Rules that need other data (past dates, overlaps) live in the domain.
 */
final readonly class CreateVacationRequest
{
    public const int MAX_REASON_LENGTH = 255;

    private function __construct(
        private DateTime $from,
        private DateTime $to,
        private string $reason,
    ) {
    }

    /**
     * @throws InvalidArgumentException when a field is missing, of the wrong type or malformed
     * @throws \JsonException when the body is not valid JSON
     */
    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = RequestBody::fromRequest($request);

        $reason = trim($body->requiredString('reason'));
        if ($reason === '') {
            throw new InvalidArgumentException("'reason' must not be empty.");
        }
        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new InvalidArgumentException("'reason' must be at most " . self::MAX_REASON_LENGTH . ' characters.');
        }

        return new self($body->requiredDate('from'), $body->requiredDate('to'), $reason);
    }

    public function getFrom(): DateTime
    {
        return $this->from;
    }

    public function getTo(): DateTime
    {
        return $this->to;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Requests;

use App\Shared\RequestBody;
use DateTime;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The input for submitting a vacation request, validated for shape and format.
 * Rules that need other data (past dates, overlaps) live in the domain.
 */
#[OA\Schema(
    schema: 'CreateVacationBody',
    description: 'A new vacation request. It must start today or later, span at most 60 days, and not overlap one of the employee\'s pending or approved requests.',
    type: 'object',
    required: ['from', 'to', 'reason'],
    properties: [
        new OA\Property(property: 'from', description: 'First day off.', type: 'string', format: 'date', example: '2030-07-01'),
        new OA\Property(property: 'to', description: 'Last day off, included. Not before `from`.', type: 'string', format: 'date', example: '2030-07-10'),
        new OA\Property(property: 'reason', type: 'string', minLength: 1, maxLength: 255, example: 'Summer holiday'),
    ],
)]
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

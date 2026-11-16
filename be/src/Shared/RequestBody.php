<?php

declare(strict_types=1);

namespace App\Shared;

use BackedEnum;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads a JSON request body with strict types, so a wrong or missing field is a
 * clear 400 for the client instead of a PHP error (and a 500) further down.
 *
 * Messages name the field exactly as the client sends it.
 */
final readonly class RequestBody
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * @throws JsonException when the body is not valid JSON
     * @throws InvalidArgumentException when the body is JSON but not an object
     */
    public static function fromRequest(ServerRequestInterface $request): self
    {
        $decoded = json_decode((string) $request->getBody(), false, 512, JSON_THROW_ON_ERROR);

        if (!is_object($decoded)) {
            throw new InvalidArgumentException('The request body must be a JSON object.');
        }

        return new self(get_object_vars($decoded));
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->data) && $this->data[$field] !== null;
    }

    /**
     * @throws InvalidArgumentException when the field is missing, null or not a string
     */
    public function requiredString(string $field): string
    {
        if (!$this->has($field)) {
            throw new InvalidArgumentException("'$field' is required.");
        }

        return $this->stringValue($field);
    }

    /**
     * @throws InvalidArgumentException when the field is present but not a string
     */
    public function optionalString(string $field): ?string
    {
        return $this->has($field) ? $this->stringValue($field) : null;
    }

    /**
     * A string field that must be one of an enum's values (e.g. a role).
     *
     * @template T of BackedEnum
     * @param class-string<T> $enum
     * @return T|null null when the field is absent
     * @throws InvalidArgumentException when the value is not a string or not one of the cases
     */
    public function optionalEnum(string $field, string $enum): ?BackedEnum
    {
        $value = $this->optionalString($field);
        if ($value === null) {
            return null;
        }

        $case = $enum::tryFrom($value);
        if ($case === null) {
            $allowed = implode(', ', array_map(fn (BackedEnum $case) => (string) $case->value, $enum::cases()));
            throw new InvalidArgumentException("'$field' must be one of: $allowed.");
        }

        return $case;
    }

    /**
     * A calendar date written exactly as YYYY-MM-DD (no time, no relative words such
     * as "tomorrow") that exists on the calendar. Returned as midnight UTC.
     *
     * @throws InvalidArgumentException
     */
    public function requiredDate(string $field): DateTime
    {
        $value = $this->requiredString($field);

        $date = DateTime::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        // The round trip rejects dates that PHP would silently roll over, like 2026-02-31.
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("'$field' must be a date in the format YYYY-MM-DD.");
        }

        return $date;
    }

    private function stringValue(string $field): string
    {
        $value = $this->data[$field];

        if (!is_string($value)) {
            throw new InvalidArgumentException("'$field' must be a string.");
        }

        return $value;
    }
}

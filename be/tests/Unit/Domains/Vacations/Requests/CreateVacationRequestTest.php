<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Requests;

use App\Domains\Vacations\Requests\CreateVacationRequest;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreateVacationRequestTest extends TestCase
{
    public function testParsesAValidRequest(): void
    {
        $request = CreateVacationRequest::fromRequest(
            $this->requestWithBody('{"from":"2026-07-01","to":"2026-07-10","reason":"  Summer holiday  "}')
        );

        $this->assertSame('2026-07-01', $request->getFrom()->format('Y-m-d'));
        $this->assertSame('2026-07-10', $request->getTo()->format('Y-m-d'));
        $this->assertSame('UTC', $request->getFrom()->getTimezone()->getName());
        $this->assertSame('Summer holiday', $request->getReason(), 'the reason is trimmed');
    }

    public function testAReasonMayRunOverSeveralLinesBecauseTheFormIsATextArea(): void
    {
        $request = CreateVacationRequest::fromRequest(
            $this->requestWithBody('{"from":"2026-07-01","to":"2026-07-02","reason":"Line one\r\n\tindented line two"}')
        );

        $this->assertSame("Line one\r\n\tindented line two", $request->getReason());
    }

    public function testAcceptsAReasonOfExactlyTheMaximumLength(): void
    {
        $reason = str_repeat('x', CreateVacationRequest::MAX_REASON_LENGTH);

        $request = CreateVacationRequest::fromRequest(
            $this->requestWithBody(json_encode(['from' => '2026-07-01', 'to' => '2026-07-02', 'reason' => $reason], JSON_THROW_ON_ERROR))
        );

        $this->assertSame($reason, $request->getReason());
    }

    public function testCountsReasonLengthInCharactersNotBytes(): void
    {
        $reason = str_repeat('é', CreateVacationRequest::MAX_REASON_LENGTH); // 255 characters, 510 bytes

        $request = CreateVacationRequest::fromRequest(
            $this->requestWithBody(json_encode(['from' => '2026-07-01', 'to' => '2026-07-02', 'reason' => $reason], JSON_THROW_ON_ERROR))
        );

        $this->assertSame($reason, $request->getReason());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidInputWithAClearMessage(array $payload, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        CreateVacationRequest::fromRequest($this->requestWithBody(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        $valid = ['from' => '2026-07-01', 'to' => '2026-07-10', 'reason' => 'Trip'];

        return [
            'missing from' => [array_diff_key($valid, ['from' => 1]), "'from' is required."],
            'missing to' => [array_diff_key($valid, ['to' => 1]), "'to' is required."],
            'missing reason' => [array_diff_key($valid, ['reason' => 1]), "'reason' is required."],
            'null date' => [[...$valid, 'from' => null], "'from' is required."],
            'relative date' => [[...$valid, 'from' => 'tomorrow'], "'from' must be a date in the format YYYY-MM-DD."],
            'relative offset' => [[...$valid, 'to' => '+3 days'], "'to' must be a date in the format YYYY-MM-DD."],
            'date with time' => [[...$valid, 'from' => '2026-07-01 10:00'], "'from' must be a date in the format YYYY-MM-DD."],
            'wrong separator' => [[...$valid, 'from' => '01/07/2026'], "'from' must be a date in the format YYYY-MM-DD."],
            'day that does not exist' => [[...$valid, 'from' => '2026-02-30'], "'from' must be a date in the format YYYY-MM-DD."],
            'month that does not exist' => [[...$valid, 'to' => '2026-13-01'], "'to' must be a date in the format YYYY-MM-DD."],
            'numeric date' => [[...$valid, 'from' => 20260701], "'from' must be a string."],
            'numeric reason' => [[...$valid, 'reason' => 12345], "'reason' must be a string."],
            'array reason' => [[...$valid, 'reason' => ['a']], "'reason' must be a string."],
            'empty reason' => [[...$valid, 'reason' => ''], "'reason' must not be empty."],
            'blank reason' => [[...$valid, 'reason' => "   \n"], "'reason' must not be empty."],
            'reason with a NUL' => [[...$valid, 'reason' => "Trip\0"], "'reason' must be text without control characters (line breaks are allowed)."],
            'reason with an escape character' => [[...$valid, 'reason' => "Trip\x1b[31m"], "'reason' must be text without control characters (line breaks are allowed)."],
            'reason with a delete character' => [[...$valid, 'reason' => "Trip\x7f"], "'reason' must be text without control characters (line breaks are allowed)."],
            'reason one character too long' => [
                [...$valid, 'reason' => str_repeat('x', CreateVacationRequest::MAX_REASON_LENGTH + 1)],
                "'reason' must be at most 255 characters.",
            ],
        ];
    }

    #[DataProvider('bodiesThatAreNotObjects')]
    public function testRejectsABodyThatIsNotAJsonObject(string $body): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The request body must be a JSON object.');

        CreateVacationRequest::fromRequest($this->requestWithBody($body));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function bodiesThatAreNotObjects(): array
    {
        return ['a list' => ['[1,2]'], 'a string' => ['"text"'], 'a number' => ['42'], 'null' => ['null']];
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        CreateVacationRequest::fromRequest($this->requestWithBody('{not json'));
    }

    private function requestWithBody(string $json): ServerRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($json);

        return new ServerRequest([], [], null, null, $body);
    }
}

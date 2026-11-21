<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\RequestBody;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestBodyTest extends TestCase
{
    public function testReadsStringsAndTellsPresentFromMissingFields(): void
    {
        $body = $this->body('{"name":"Ann","nothing":null}');

        $this->assertSame('Ann', $body->requiredString('name'));
        $this->assertSame('Ann', $body->optionalString('name'));
        $this->assertTrue($body->has('name'));
        $this->assertFalse($body->has('nothing'), 'null counts as absent');
        $this->assertFalse($body->has('missing'));
        $this->assertNull($body->optionalString('missing'));
        $this->assertNull($body->optionalString('nothing'));
    }

    public function testAnEmptyObjectIsAValidBody(): void
    {
        $this->assertFalse($this->body('{}')->has('anything'));
    }

    public function testRequiredStringNamesTheMissingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'email' is required.");

        $this->body('{}')->requiredString('email');
    }

    public function testOptionalStringStillRejectsTheWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'password' must be a string.");

        $this->body('{"password":123456789}')->optionalString('password');
    }

    public function testEmptyStringIsAString(): void
    {
        $this->assertSame('', $this->body('{"password":""}')->optionalString('password'));
    }

    // ---- Text a person types: one line (a name) or free text (a reason, a message) ---------------

    public function testALineOfTextIsReadAsIs(): void
    {
        $body = $this->body('{"name":"Zoë Müller – 山田"}');

        $this->assertSame('Zoë Müller – 山田', $body->requiredLine('name'));
        $this->assertSame('Zoë Müller – 山田', $body->optionalLine('name'));
        $this->assertNull($body->optionalLine('missing'));
    }

    #[DataProvider('charactersThatBreakALine')]
    public function testALineWithAControlCharacterIsRefused(string $character): void
    {
        $body = $this->body(json_encode(['name' => "Ann{$character}Lee"], JSON_THROW_ON_ERROR));

        foreach (['requiredLine', 'optionalLine'] as $read) {
            try {
                $body->$read('name');
                $this->fail("$read should refuse it.");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("'name' must be one line of text without control characters.", $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function charactersThatBreakALine(): array
    {
        return [
            'line feed' => ["\n"],
            'carriage return' => ["\r"],
            'tab' => ["\t"],
            'NUL' => ["\0"],
            'escape' => ["\x1b"],
            'delete' => ["\x7f"],
            'next line (C1)' => ["\u{0085}"],
            'line separator' => ["\u{2028}"],
            'paragraph separator' => ["\u{2029}"],
        ];
    }

    public function testFreeTextMayHaveLineBreaksAndTabs(): void
    {
        $body = $this->body('{"reason":"One\r\n\tTwo\nThree"}');

        $this->assertSame("One\r\n\tTwo\nThree", $body->requiredText('reason'));
        $this->assertSame("One\r\n\tTwo\nThree", $body->optionalText('reason'));
        $this->assertNull($body->optionalText('missing'));
    }

    #[DataProvider('charactersThatNeverBelongInText')]
    public function testFreeTextWithOtherControlCharactersIsRefused(string $character): void
    {
        $body = $this->body(json_encode(['reason' => "Trip{$character}"], JSON_THROW_ON_ERROR));

        foreach (['requiredText', 'optionalText'] as $read) {
            try {
                $body->$read('reason');
                $this->fail("$read should refuse it.");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("'reason' must be text without control characters (line breaks are allowed).", $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function charactersThatNeverBelongInText(): array
    {
        return ['NUL' => ["\0"], 'bell' => ["\x07"], 'escape' => ["\x1b"], 'delete' => ["\x7f"], 'next line (C1)' => ["\u{0085}"]];
    }

    public function testTheTextReadersStillRequireAStringAndNamePresence(): void
    {
        foreach (['requiredLine', 'requiredText'] as $read) {
            try {
                $this->body('{}')->$read('field');
                $this->fail('A missing field should be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame("'field' is required.", $e->getMessage());
            }

            try {
                $this->body('{"field":["x"]}')->$read('field');
                $this->fail('A list should be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame("'field' must be a string.", $e->getMessage());
            }
        }
    }

    public function testDatesAreMidnightUtc(): void
    {
        $date = $this->body('{"day":"2028-02-29"}')->requiredDate('day');

        $this->assertSame('2028-02-29 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $date->getTimezone()->getName());
    }

    public function testRejectsAnImpossibleLeapDay(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->body('{"day":"2027-02-29"}')->requiredDate('day');
    }

    private function body(string $json): RequestBody
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($json);

        return RequestBody::fromRequest(new ServerRequest([], [], null, null, $stream));
    }
}

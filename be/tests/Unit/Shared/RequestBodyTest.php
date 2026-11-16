<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\RequestBody;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
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

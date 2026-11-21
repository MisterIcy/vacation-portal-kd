<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Authentication\Requests;

use App\Domains\Authentication\Requests\LoginRequest;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoginRequestTest extends TestCase
{
    private function request(string $json): ServerRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($json);

        return new ServerRequest([], [], null, null, $body);
    }

    public function testReadsTheEmailAndThePassword(): void
    {
        $login = new LoginRequest($this->request('{"email":"ada@example.com","password":"secret123"}'));

        $this->assertSame('ada@example.com', $login->getEmail());
        $this->assertSame('secret123', $login->getPassword());
    }

    public function testAnEmptyPasswordIsStillAStringAndLeftToTheCredentialCheck(): void
    {
        $login = new LoginRequest($this->request('{"email":"ada@example.com","password":""}'));

        $this->assertSame('', $login->getPassword());
    }

    /**
     * Every one of these used to reach a typed property or an array offset inside the application, and
     * a wrong type there is an error (a 500), not a refusal. They are all the caller's mistake: 400.
     */
    #[DataProvider('malformedBodies')]
    public function testABodyThatIsNotTwoStringsIsRefusedAsInvalidInput(string $json, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new LoginRequest($this->request($json));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedBodies(): array
    {
        return [
            'email is a list' => ['{"email":[],"password":"x"}', "'email' must be a string."],
            'email is an object' => ['{"email":{"a":1},"password":"x"}', "'email' must be a string."],
            'email is a number' => ['{"email":123,"password":"x"}', "'email' must be a string."],
            'password is an object' => ['{"email":"a@b.c","password":{"x":1}}', "'password' must be a string."],
            'password is a list' => ['{"email":"a@b.c","password":["x"]}', "'password' must be a string."],
            'password is a boolean' => ['{"email":"a@b.c","password":true}', "'password' must be a string."],
            'email is missing' => ['{"password":"x"}', "'email' is required."],
            'password is missing' => ['{"email":"a@b.c"}', "'password' is required."],
            'email is null' => ['{"email":null,"password":"x"}', "'email' is required."],
            'body is a list' => ['[]', 'The request body must be a JSON object.'],
            'body is a string' => ['"text"', 'The request body must be a JSON object.'],
            'body is a number' => ['5', 'The request body must be a JSON object.'],
            'body is null' => ['null', 'The request body must be a JSON object.'],
        ];
    }

    public function testABodyThatIsNotJsonIsAJsonException(): void
    {
        $this->expectException(JsonException::class);

        new LoginRequest($this->request('{not json'));
    }
}

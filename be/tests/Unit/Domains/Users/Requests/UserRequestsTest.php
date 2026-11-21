<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Users\Requests;

use App\Domains\Users\Models\UserRole;
use App\Domains\Users\Requests\CreateUserRequest;
use App\Domains\Users\Requests\UpdateUserRequest;
use InvalidArgumentException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserRequestsTest extends TestCase
{
    public function testCreateParsesAValidRequestAndNormalizesTheEmail(): void
    {
        $request = CreateUserRequest::fromRequest($this->requestWithBody(
            '{"email":"  Jane.Doe@Example.COM ","password":"password123","name":"  Jane Doe ","employee_code":"0001234","role":"manager"}'
        ));

        $this->assertSame('jane.doe@example.com', $request->getEmail());
        $this->assertSame('Jane Doe', $request->getName());
        $this->assertSame('password123', $request->getPassword(), 'the password is never altered');
        $this->assertSame('0001234', $request->getEmployeeCode(), 'leading zeros are kept');
        $this->assertSame(UserRole::Manager, $request->getRole());
    }

    public function testCreateDefaultsTheRoleToEmployee(): void
    {
        $request = CreateUserRequest::fromRequest($this->requestWithBody(
            '{"email":"a@example.com","password":"password123","name":"Ann","employee_code":"1234567"}'
        ));

        $this->assertSame(UserRole::Employee, $request->getRole());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidCreatePayloads')]
    public function testCreateRejectsInvalidInputWithAClearMessage(array $payload, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        CreateUserRequest::fromRequest($this->requestWithBody(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreatePayloads(): array
    {
        $valid = ['email' => 'a@example.com', 'password' => 'password123', 'name' => 'Ann', 'employee_code' => '1234567'];

        return [
            'missing email' => [array_diff_key($valid, ['email' => 1]), "'email' is required."],
            'missing password' => [array_diff_key($valid, ['password' => 1]), "'password' is required."],
            'missing name' => [array_diff_key($valid, ['name' => 1]), "'name' is required."],
            'missing employee code' => [array_diff_key($valid, ['employee_code' => 1]), "'employee_code' is required."],
            'numeric employee code' => [[...$valid, 'employee_code' => 1234567], "'employee_code' must be a string."],
            'array name' => [[...$valid, 'name' => ['Ann']], "'name' must be a string."],
            'name with a line break' => [[...$valid, 'name' => "Ann\r\nBcc: x@example.com"], "'name' must be one line of text without control characters."],
            'name with a tab' => [[...$valid, 'name' => "Ann\tLee"], "'name' must be one line of text without control characters."],
            'name with a NUL' => [[...$valid, 'name' => "Ann\0"], "'name' must be one line of text without control characters."],
            'name with a Unicode line separator' => [[...$valid, 'name' => "Ann\u{2028}Lee"], "'name' must be one line of text without control characters."],
            'unknown role' => [[...$valid, 'role' => 'admin'], "'role' must be one of: manager, employee."],
            'role of the wrong type' => [[...$valid, 'role' => 1], "'role' must be a string."],
        ];
    }

    public function testUpdateTreatsMissingFieldsAsUnchanged(): void
    {
        $request = UpdateUserRequest::fromRequest($this->requestWithBody('{"name":"New Name"}'));

        $this->assertSame('New Name', $request->getName());
        $this->assertNull($request->getEmail());
        $this->assertNull($request->getPassword());
        $this->assertNull($request->getRole());
    }

    public function testUpdateTreatsABlankPasswordAsKeepTheCurrentOne(): void
    {
        $request = UpdateUserRequest::fromRequest($this->requestWithBody('{"name":"Ann","password":""}'));

        $this->assertNull($request->getPassword());
    }

    public function testUpdateKeepsANewPasswordAndNormalizesTheEmail(): void
    {
        $request = UpdateUserRequest::fromRequest($this->requestWithBody(
            '{"email":" New@Example.com ","password":"new-password-1","role":"manager"}'
        ));

        $this->assertSame('new@example.com', $request->getEmail());
        $this->assertSame('new-password-1', $request->getPassword());
        $this->assertSame(UserRole::Manager, $request->getRole());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidUpdatePayloads')]
    public function testUpdateRejectsInvalidInputWithAClearMessage(array $payload, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        UpdateUserRequest::fromRequest($this->requestWithBody(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidUpdatePayloads(): array
    {
        return [
            'numeric password' => [['password' => 123456789], "'password' must be a string."],
            'numeric name' => [['name' => 42], "'name' must be a string."],
            'name with a line break' => [['name' => "Ann\nLee"], "'name' must be one line of text without control characters."],
            'unknown role' => [['role' => 'superuser'], "'role' must be one of: manager, employee."],
        ];
    }

    private function requestWithBody(string $json): ServerRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($json);

        return new ServerRequest([], [], null, null, $body);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\Models\UserRole;
use App\Shared\RequestBody;
use InvalidArgumentException;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The input for creating a user, validated for shape. The values themselves
 * (email format, password length, 7-digit employee code) are checked by UserModel.
 */
#[OA\Schema(
    schema: 'CreateUserBody',
    description: 'A new user. The email and the employee code must not be in use yet.',
    type: 'object',
    required: ['email', 'password', 'name', 'employee_code'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
        new OA\Property(property: 'password', description: 'At least 8 characters and at most 1024 bytes.', type: 'string', format: 'password', minLength: 8, example: 'your-password-here'),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'employee_code', description: 'Seven digits.', type: 'string', pattern: '^\d{7}$', example: '1234567'),
        new OA\Property(property: 'role', ref: '#/components/schemas/UserRole', description: 'Defaults to employee.'),
    ],
)]
final readonly class CreateUserRequest
{
    private function __construct(
        private string $email,
        private string $password,
        private string $name,
        private string $employeeCode,
        private UserRole $role,
    ) {
    }

    /**
     * @throws InvalidArgumentException when a field is missing or of the wrong type, or the role is unknown
     * @throws JsonException when the body is not valid JSON
     */
    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = RequestBody::fromRequest($request);

        return new self(
            strtolower(trim($body->requiredString('email'))),
            $body->requiredString('password'),
            trim($body->requiredLine('name')),
            $body->requiredString('employee_code'),
            $body->optionalEnum('role', UserRole::class) ?? UserRole::Employee,
        );
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmployeeCode(): string
    {
        return $this->employeeCode;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }
}

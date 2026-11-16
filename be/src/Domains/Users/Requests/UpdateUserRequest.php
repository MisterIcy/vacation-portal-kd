<?php

declare(strict_types=1);

namespace App\Domains\Users\Requests;

use App\Domains\Users\Models\UserRole;
use App\Shared\RequestBody;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The input for updating a user. Every field is optional and a missing one means
 * "leave unchanged". The employee code cannot be changed, so it is not read.
 */
final readonly class UpdateUserRequest
{
    private function __construct(
        private ?string $email,
        private ?string $password,
        private ?string $name,
        private ?UserRole $role,
    ) {
    }

    /**
     * @throws InvalidArgumentException when a field is of the wrong type or the role is unknown
     * @throws JsonException when the body is not valid JSON
     */
    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = RequestBody::fromRequest($request);

        $email = $body->optionalString('email');
        $name = $body->optionalString('name');
        $password = $body->optionalString('password');

        return new self(
            $email === null ? null : strtolower(trim($email)),
            // The edit form leaves the password blank to keep the current one.
            $password === '' ? null : $password,
            $name === null ? null : trim($name),
            $body->optionalEnum('role', UserRole::class),
        );
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Null means the password stays as it is.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRole(): ?UserRole
    {
        return $this->role;
    }
}

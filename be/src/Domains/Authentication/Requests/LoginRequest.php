<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Requests;

use App\Shared\PasswordPolicy;
use App\Shared\RequestBody;
use InvalidArgumentException;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Schema(
    title: 'LoginRequest',
    description: 'Data structure for user authentication.',
    type: 'object',
    required: ['email', 'password'],
)]
class LoginRequest
{
    #[OA\Property(
        description: 'The user\'s email address',
        example: 'john.doe@example.com'
    )]
    private string $email;
    #[OA\Property(
        description: 'The user\'s password (at most 1024 bytes)',
        format: 'password',
        example: 'your-password-here',
    )]
    private string $password;

    /**
     * Read like every other request body: a field of the wrong type is a 400, never an error inside
     * the application. This endpoint is public, so it is the one that most needs to get this right.
     *
     * @throws InvalidArgumentException when the body is not a JSON object, a field is missing or not a string, or the password is too long
     * @throws JsonException when the body is not valid JSON
     */
    public function __construct(ServerRequestInterface $request)
    {
        $body = RequestBody::fromRequest($request);

        $this->email = $body->requiredString('email');
        $this->password = $body->requiredString('password');

        // Before anything is hashed: the endpoint is public, and hashing is the expensive part.
        PasswordPolicy::assertNotTooLong($this->password);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}

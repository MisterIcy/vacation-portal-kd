<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Controllers;

use App\Domains\Authentication\Repositories\AuthenticationRepository;
use App\Domains\Authentication\Requests\LoginRequest;
use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Authentication\Services\AuthenticationService;
use App\Shared\JsonErrorResponse;
use App\Shared\Ports\PasswordHasher;
use InvalidArgumentException;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use JsonException;

readonly class AuthenticationController
{
    public function __construct(
        private AuthenticationRepository $authenticationRepository,
        private AuthenticationService $authenticationService,
        private PasswordHasher $passwordHasher,
        private AccessControlService $accessControl,
    ) {
    }

    /**
     * Handle user login
     *
     * @throws JsonException
     */
    #[OA\Post(
        path: '/auth/login',
        operationId: 'loginUser',
        description: 'Verifies user credentials and returns a JWT on success.',
        summary: 'User login endpoint',
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(ref: '#/components/schemas/LoginRequest'),
                ),
            ],
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'token',
                            type: 'string',
                            example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                ref: '#/components/responses/400',
                response: 400,
            ),
            new OA\Response(
                ref: '#/components/responses/401',
                response: 401,
            ),
            new OA\Response(
                ref: '#/components/responses/500',
                response: 500,
            ),
        ],
    )]
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $loginRequest = new LoginRequest($request);

        // Find user by email
        try {
            $user = $this->authenticationRepository->findByEmail($loginRequest->getEmail());
        } catch (InvalidArgumentException) {
            // Do the same expensive work as for a wrong password, so that the time this takes does
            // not tell an attacker which email addresses have an account.
            $this->passwordHasher->hash($loginRequest->getPassword());

            return new JsonErrorResponse(401, 'Invalid credentials');
        }

        // Verify password
        if (!$user->getHashedPassword() || !$this->passwordHasher->verify($loginRequest->getPassword(), $user->getHashedPassword())) {
            return new JsonErrorResponse(401, 'Invalid credentials');
        }

        return new JsonResponse([
            'token' => $this->authenticationService->createToken($user),
        ], 200);
    }

    /**
     * Handle token renewal
     */
    public function renew(ServerRequestInterface $request): ResponseInterface
    {
        // The middleware has already validated the token and loaded the person as they are now,
        // so the new token carries their current name, email and role, not the old token's.
        $authenticatedUser = $this->accessControl->getAuthenticatedUserModelFromRequest($request);

        return new JsonResponse([
            'token' => $this->authenticationService->createToken($authenticatedUser),
        ], 200);
    }
}

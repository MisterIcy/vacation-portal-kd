<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Users\Models\UserModel;
use App\Domains\Users\Repositories\UsersRepository;
use App\Domains\Users\Requests\CreateUserRequest;
use App\Domains\Users\Requests\UpdateUserRequest;
use App\Shared\JsonErrorResponse;
use DateMalformedStringException;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Http\Exception\ForbiddenException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

readonly class UsersController
{
    public function __construct(private UsersRepository $usersRepository, private AccessControlService $accessControl)
    {
    }

    /**
     * @throws JsonException|DateMalformedStringException|ForbiddenException
     */
    #[OA\Post(
        path: '/users',
        operationId: 'createUser',
        description: 'Manager only.',
        summary: 'Create a user',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateUserBody'),
        ),
        tags: ['User'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'The new user.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserModel'),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireManager($request);

        $input = CreateUserRequest::fromRequest($request);

        $userModel = new UserModel(
            null,
            $input->getEmail(),
            $input->getPassword(),
            $input->getName(),
            $input->getEmployeeCode(),
            $input->getRole()
        );

        $userModel = $this->usersRepository->create($userModel);

        return new JsonResponse($userModel, 201);
    }

    /**
     * @throws DateMalformedStringException|ForbiddenException
     */
    #[OA\Get(
        path: '/users',
        operationId: 'listUsers',
        description: 'Manager only.',
        summary: 'List all users',
        security: [['BearerAuth' => []]],
        tags: ['User'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Every user.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/UserModel')),
            ),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireManager($request);

        $usersCollection = $this->usersRepository->findAll();

        return new JsonResponse($usersCollection, 200);
    }

    /**
     * @param array{id: int} $params
     * @throws DateMalformedStringException|ForbiddenException
     */
    #[OA\Get(
        path: '/users/{id}',
        operationId: 'getUserById',
        description: 'Retrieve the details of the user with the provided id.',
        summary: 'Retrieve user details',
        security: [
            ['BearerAuth' => []]
        ],
        tags: ['User'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The unique ID of the user to retrieve.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 42,
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved user',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(ref: '#/components/schemas/UserModel'),
                    ),
                ],
            ),
            new OA\Response(
                ref: '#/components/responses/400',
                response: 400,
            ),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(
                ref: '#/components/responses/403',
                response: 403,
            ),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(
                ref: '#/components/responses/500',
                response: 500,
            ),
        ],
    )]
    public function get(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->requireManager($request);

        $userModel = $this->usersRepository->findById((int) $params['id']);

        return new JsonResponse($userModel, 200);
    }

    /**
     * @param array{id: int} $params
     * @throws JsonException|DateMalformedStringException|ForbiddenException
     */
    #[OA\Put(
        path: '/users/{id}',
        operationId: 'updateUser',
        description: 'Manager only. Fields that are not sent stay as they are.',
        summary: 'Update a user',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserBody'),
        ),
        tags: ['User'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The user as updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserModel'),
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/409', response: 409),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function update(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->requireManager($request);

        $input = UpdateUserRequest::fromRequest($request);
        $existingUserModel = $this->usersRepository->findById((int) $params['id']);

        // Giving up their own manager role would lock them out immediately, and if they were the last
        // manager nobody could administer users any more. Someone else has to do it.
        $caller = $this->accessControl->getAuthenticatedUserModelFromRequest($request);
        $newRole = $input->getRole();
        if ($existingUserModel->getId() === $caller->getId() && $newRole !== null && $newRole !== $existingUserModel->getRole()) {
            throw new InvalidArgumentException('You cannot change your own role');
        }

        // A field that was not sent keeps its current value. No password means "keep
        // the current one" (the repository never writes null); a new one is validated by the model.
        $userModel = new UserModel(
            $existingUserModel->getId(),
            $input->getEmail() ?? $existingUserModel->getEmail(),
            $input->getPassword(),
            $input->getName() ?? $existingUserModel->getName(),
            $existingUserModel->getEmployeeCode(),
            $input->getRole() ?? $existingUserModel->getRole()
        );

        $userModel = $this->usersRepository->update($userModel);

        return new JsonResponse($userModel, 200);
    }

    /**
     * @param array{id: int} $params
     * @throws ForbiddenException
     */
    #[OA\Delete(
        path: '/users/{id}',
        operationId: 'deleteUser',
        description: 'Manager only. A manager cannot delete their own account. The user\'s vacation requests are deleted with them.',
        summary: 'Delete a user',
        security: [['BearerAuth' => []]],
        tags: ['User'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/UserId')],
        responses: [
            new OA\Response(response: 204, description: 'The user was deleted.'),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $this->requireManager($request);
        $authenticatedUser = $this->accessControl->getAuthenticatedUserModelFromRequest($request);
        $userId = (int) $params['id'];

        if ($authenticatedUser->getId() === $userId) {
            return new JsonErrorResponse(400, 'You cannot delete your own account');
        }

        $this->usersRepository->delete($userId);

        return new JsonResponse('', 204);
    }

    /**
     * @param ServerRequestInterface $request
     * @return void
     * @throws ForbiddenException
     */
    public function requireManager(ServerRequestInterface $request): void
    {
        if (!$this->accessControl->isManagerFromRequest($request)) {
            throw new ForbiddenException();
        }
    }
}

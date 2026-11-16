<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Services;

use App\Domains\Authentication\Middleware\AuthenticationMiddleware;
use App\Domains\Authentication\Models\AuthenticatedUserModel;
use App\Domains\Users\Models\UserRole;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers "who is calling, and in which role" for an endpoint.
 *
 * Identifying the caller is the AuthenticationMiddleware's job and happens before any
 * endpoint runs; this reads its result. It does not look at the token itself.
 */
readonly class AccessControlService
{
    public function isEmployeeFromRequest(ServerRequestInterface $request): bool
    {
        return $this->getAuthenticatedUserModelFromRequest($request)->getRole() === UserRole::Employee;
    }

    public function isManagerFromRequest(ServerRequestInterface $request): bool
    {
        return $this->getAuthenticatedUserModelFromRequest($request)->getRole() === UserRole::Manager;
    }

    /**
     * @throws LogicException when the endpoint is not behind the authentication middleware
     */
    public function getAuthenticatedUserModelFromRequest(ServerRequestInterface $request): AuthenticatedUserModel
    {
        $user = $request->getAttribute(AuthenticationMiddleware::ATTRIBUTE);

        if (!$user instanceof AuthenticatedUserModel) {
            // A route that forgot the middleware must fail closed, never act as an anonymous user.
            throw new LogicException('No authenticated user on the request; the route is missing the authentication middleware.');
        }

        return $user;
    }
}

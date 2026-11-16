<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Middleware;

use App\Domains\Authentication\Repositories\AuthenticationRepository;
use App\Domains\Authentication\Services\AuthenticationService;
use App\Shared\JsonErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Works out who is making the request, once, before any endpoint runs.
 *
 * The token proves who someone was when it was issued. The person is then looked up again,
 * so what the endpoint sees is the *current* state: a deleted user is refused even though
 * their token has not expired, and a demoted manager has an employee's rights straight away.
 *
 * On success the user is attached to the request as the ATTRIBUTE; otherwise the answer is 401.
 * Whether that user may do what they ask (403) is for the endpoint to decide.
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'authenticated_user';

    public function __construct(
        private AuthenticationService $tokens,
        private AuthenticationRepository $users,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = self::bearerToken($request);
        if ($token === null) {
            return new JsonErrorResponse(401, 'Missing or invalid Authorization header');
        }

        try {
            $claims = $this->tokens->parseToken($token);
        } catch (Throwable) {
            return new JsonErrorResponse(401, 'Invalid or expired token');
        }

        $current = $this->users->findById($claims->getId());
        if ($current === null) {
            // Same answer as for a bad token: do not tell the caller which accounts exist or were removed.
            return new JsonErrorResponse(401, 'Invalid or expired token');
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $current));
    }

    private static function bearerToken(ServerRequestInterface $request): ?string
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($request->getHeaderLine('Authorization')), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}

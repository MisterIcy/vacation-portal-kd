<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Routes;

use App\Domains\Authentication\Controllers\AuthenticationController;
use League\Route\RouteCollectionInterface;
use Psr\Http\Server\MiddlewareInterface;

readonly class AuthenticationRoutes
{
    public function __construct(private RouteCollectionInterface $router, private MiddlewareInterface $authentication)
    {
    }

    public function register(): void
    {
        // The only public endpoint: it is how a caller obtains a token in the first place.
        $this->router->post('/auth/login', [AuthenticationController::class, 'login']);

        $this->router->post('/auth/renew', [AuthenticationController::class, 'renew'])->middleware($this->authentication);
    }
}

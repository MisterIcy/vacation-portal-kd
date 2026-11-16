<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;
use JsonException;
use League\Route\Http\Exception\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (InvalidArgumentException|JsonException $e) {
            return new JsonErrorResponse(400, $e->getMessage());
        } catch (ForbiddenException|AccessDenied $e) {
            return new JsonErrorResponse(403, $e->getMessage());
        } catch (Throwable $e) {
            // The client only gets a generic message, so the cause has to be recorded here or it is lost.
            // It goes to the error log (stderr in the container), never into the response.
            error_log(sprintf(
                '%s %s %s failed: %s: %s in %s:%d',
                date(DATE_ATOM),
                $request->getMethod(),
                $request->getUri()->getPath(),
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));

            return new JsonErrorResponse(500, 'Internal Server Error');
        }
    }
}

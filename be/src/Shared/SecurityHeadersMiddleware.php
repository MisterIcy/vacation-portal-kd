<?php

declare(strict_types=1);

namespace App\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Browser-facing protections for every response of this JSON API.
 *
 * It only ever returns data, never pages, so the policy can be as strict as possible: nothing is
 * allowed to load, nothing may frame it, and responses (which can hold personal data) are not cached.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        'Cache-Control' => 'no-store',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach (self::HEADERS as $name => $value) {
            // A header the application chose deliberately is kept.
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}

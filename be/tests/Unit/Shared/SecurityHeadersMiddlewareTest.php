<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\SecurityHeadersMiddleware;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAddsTheSecurityHeadersToAResponse(): void
    {
        $response = $this->process(new JsonResponse(['ok' => true]));

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertStringContainsString("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testTheApiResponsesAreNeverCached(): void
    {
        $this->assertSame('no-store', $this->process(new JsonResponse([]))->getHeaderLine('Cache-Control'));
    }

    public function testKeepsTheStatusAndTheBody(): void
    {
        $response = $this->process(new JsonResponse(['reason_phrase' => 'Nope'], 403));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Nope', (string) $response->getBody());
    }

    public function testApplicationsOwnHeadersAreNotOverwritten(): void
    {
        $response = $this->process((new JsonResponse([]))->withHeader('Cache-Control', 'private, max-age=60'));

        $this->assertSame('private, max-age=60', $response->getHeaderLine('Cache-Control'));
    }

    private function process(ResponseInterface $inner): ResponseInterface
    {
        $handler = new class ($inner) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        return (new SecurityHeadersMiddleware())->process(new ServerRequest(), $handler);
    }
}

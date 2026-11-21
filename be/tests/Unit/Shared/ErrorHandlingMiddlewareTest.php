<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\AccessDenied;
use App\Shared\Conflict;
use App\Shared\ErrorHandlingMiddleware;
use App\Shared\NotFound;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use League\Route\Http\Exception\ForbiddenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

class ErrorHandlingMiddlewareTest extends TestCase
{
    public function testPassesASuccessfulResponseThrough(): void
    {
        $response = $this->handle(fn () => new JsonResponse(['ok' => true], 201));

        $this->assertSame(201, $response->getStatusCode());
    }

    #[DataProvider('mappedErrors')]
    public function testMapsDomainErrorsToTheirStatus(Throwable $error, int $expectedStatus, string $expectedMessage): void
    {
        $response = $this->handle(function () use ($error): never {
            throw $error;
        });

        $this->assertSame($expectedStatus, $response->getStatusCode());
        /** @var array{status_code: int, reason_phrase: string} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($expectedStatus, $body['status_code']);
        $this->assertSame($expectedMessage, $body['reason_phrase']);
    }

    /**
     * @return array<string, array{Throwable, int, string}>
     */
    public static function mappedErrors(): array
    {
        return [
            'invalid input' => [new InvalidArgumentException('Name must not be empty.'), 400, 'Name must not be empty.'],
            'malformed json' => [new JsonException('Syntax error'), 400, 'Syntax error'],
            'a role that is not allowed' => [AccessDenied::forRole(), 403, 'Forbidden'],
            'something that does not exist' => [new NotFound('Vacation with ID 5 not found.'), 404, 'Vacation with ID 5 not found.'],
            'a clash with the current state' => [new Conflict('Email is already in use.'), 409, 'Email is already in use.'],
            'the router refusing access' => [new ForbiddenException(), 403, 'Forbidden'],
        ];
    }

    public function testHidesTheDetailsOfUnexpectedErrorsFromTheClientButLogsThem(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'errlog');
        $this->assertIsString($logFile);
        $previous = ini_set('error_log', $logFile);

        try {
            $response = $this->handle(function (): never {
                throw new RuntimeException("SQLSTATE[HY000] Access denied for user 'secret'@'db'");
            });
            $log = (string) file_get_contents($logFile);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($logFile);
        }

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('secret', (string) $response->getBody(), 'nothing internal reaches the client');
        $this->assertStringContainsString('Internal Server Error', (string) $response->getBody());

        $this->assertStringContainsString(RuntimeException::class, $log, 'the cause is recorded for the operator');
        $this->assertStringContainsString("Access denied for user 'secret'@'db'", $log);
        $this->assertStringContainsString('GET', $log);
    }

    #[DataProvider('clientErrors')]
    public function testClientErrorsAreNotLoggedAsFailures(Throwable $error): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'errlog');
        $this->assertIsString($logFile);
        $previous = ini_set('error_log', $logFile);

        try {
            $this->handle(function () use ($error): never {
                throw $error;
            });
            $log = (string) file_get_contents($logFile);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($logFile);
        }

        $this->assertSame('', $log, 'a 4xx is the client\'s mistake, not an incident');
    }

    /**
     * @return array<string, array{Throwable}>
     */
    public static function clientErrors(): array
    {
        return [
            'invalid input' => [new InvalidArgumentException('Name must not be empty.')],
            'not found' => [new NotFound('Nothing here.')],
            'conflict' => [new Conflict('Already taken.')],
            'forbidden' => [AccessDenied::forRole()],
        ];
    }

    /**
     * @param callable(): ResponseInterface $inner
     */
    private function handle(callable $inner): ResponseInterface
    {
        $handler = new class ($inner) implements RequestHandlerInterface {
            /** @param callable(): ResponseInterface $inner */
            public function __construct(private $inner)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->inner)();
            }
        };

        return (new ErrorHandlingMiddleware())->process(new ServerRequest(), $handler);
    }
}

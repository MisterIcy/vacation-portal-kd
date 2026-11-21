<?php

declare(strict_types=1);

namespace Tests\Unit\Docs;

use App\Domains\Authentication\Routes\AuthenticationRoutes;
use App\Domains\Users\Routes\UsersRoutes;
use App\Domains\Vacations\Routes\VacationsRoutes;
use League\Route\Route;
use League\Route\RouteCollectionInterface;
use League\Route\RouteCollectionTrait;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Keeps the API documentation honest: it is generated from attributes next to the code, and
 * these tests fail when a route has no documentation or the published file is out of date.
 */
class ApiSpecificationTest extends TestCase
{
    private const string SOURCES = __DIR__ . '/../../../src';
    private const string PUBLISHED = __DIR__ . '/../../../public/openapi.yaml';

    public function testEveryRouteIsDocumented(): void
    {
        $this->assertSame(self::registeredRoutes(), self::documentedOperations());
    }

    public function testEveryOperationExceptLoginNeedsAToken(): void
    {
        foreach (self::operations() as $name => $operation) {
            if ($name === 'POST /auth/login') {
                $this->assertSame(Generator::UNDEFINED, $operation->security, $name);
                continue;
            }

            $this->assertSame([['BearerAuth' => []]], $operation->security, "$name should require a token");
        }
    }

    public function testEveryOperationDescribesItsFailures(): void
    {
        foreach (self::operations() as $name => $operation) {
            $codes = array_map(static fn (OA\Response $response): string => (string) $response->response, $operation->responses);

            $this->assertContains('500', $codes, "$name should document the server error response");
            if ($name !== 'POST /auth/login') {
                $this->assertContains('401', $codes, "$name should document the unauthorized response");
            }
        }
    }

    public function testThePublishedFileIsUpToDate(): void
    {
        $generated = Generator::scan([self::SOURCES]);
        $this->assertNotNull($generated);

        $published = file_get_contents(self::PUBLISHED);
        $this->assertIsString($published);

        $this->assertSame(
            $generated->toYaml(),
            str_replace("\r\n", "\n", $published),
            'public/openapi.yaml is out of date. Run "composer build:schema" and commit the result.',
        );
    }

    /**
     * @return list<string> e.g. "GET /vacations/{id}", sorted
     */
    private static function registeredRoutes(): array
    {
        $recorder = new class () implements RouteCollectionInterface {
            use RouteCollectionTrait;

            /** @var list<string> */
            public array $routes = [];

            /**
             * @param string|list<string> $method
             * @param callable|array<mixed>|string|RequestHandlerInterface $handler
             */
            public function map(string|array $method, string $path, callable|array|string|RequestHandlerInterface $handler): Route
            {
                $verb = strtoupper(is_array($method) ? implode('|', $method) : $method);
                // League writes a parameter as {id:number}; the specification writes {id}.
                $this->routes[] = $verb . ' ' . preg_replace('/\{(\w+):[^}]+\}/', '{$1}', $path);

                return new Route($method, $path, $handler);
            }
        };

        $authentication = new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        new AuthenticationRoutes($recorder, $authentication)->register();
        new UsersRoutes($recorder)->register();
        new VacationsRoutes($recorder)->register();

        sort($recorder->routes);

        return $recorder->routes;
    }

    /**
     * @return list<string>
     */
    private static function documentedOperations(): array
    {
        $names = array_keys(self::operations());
        sort($names);

        return $names;
    }

    /**
     * @return array<string, OA\Operation> keyed by e.g. "GET /vacations/{id}"
     */
    private static function operations(): array
    {
        $generated = Generator::scan([self::SOURCES]);
        self::assertNotNull($generated);

        $operations = [];
        foreach ($generated->paths as $path) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $verb) {
                // swagger-php marks an operation a path does not have with a sentinel string, which its
                // docblocks do not admit to, so look at it as what it may really be.
                /** @var mixed $operation */
                $operation = $path->{$verb};
                if ($operation instanceof OA\Operation) {
                    $operations[strtoupper($verb) . ' ' . $path->path] = $operation;
                }
            }
        }

        return $operations;
    }
}

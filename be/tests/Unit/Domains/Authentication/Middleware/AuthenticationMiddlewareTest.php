<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Authentication\Middleware;

use App\Domains\Authentication\Middleware\AuthenticationMiddleware;
use App\Domains\Authentication\Models\AuthenticatedUserModel;
use App\Domains\Authentication\Repositories\AuthenticationRepository;
use App\Domains\Authentication\Services\AuthenticationService;
use App\Domains\Users\Models\UserRole;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthenticationMiddlewareTest extends TestCase
{
    private const string SECRET = 'a-test-secret-that-is-long-enough-for-hs256';

    private ?ServerRequestInterface $handledRequest = null;

    public function testAValidTokenOfAnExistingUserReachesTheHandlerWithTheUser(): void
    {
        $current = new AuthenticatedUserModel(7, 'Jane', 'jane@example.com', UserRole::Employee);
        $middleware = $this->middleware(findById: fn (int $id) => $id === 7 ? $current : null);

        $response = $middleware->process($this->requestWithToken($this->tokenFor(7, UserRole::Employee)), $this->handler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($current, $this->handledRequest?->getAttribute(AuthenticationMiddleware::ATTRIBUTE));
    }

    public function testTheRoleComesFromTheDatabaseNotFromTheToken(): void
    {
        // The token was issued when this person was a manager; they have since been demoted.
        $demoted = new AuthenticatedUserModel(7, 'Jane', 'jane@example.com', UserRole::Employee);
        $middleware = $this->middleware(findById: fn () => $demoted);

        $middleware->process($this->requestWithToken($this->tokenFor(7, UserRole::Manager)), $this->handler());

        $user = $this->handledRequest?->getAttribute(AuthenticationMiddleware::ATTRIBUTE);
        $this->assertInstanceOf(AuthenticatedUserModel::class, $user);
        $this->assertSame(UserRole::Employee, $user->getRole());
    }

    public function testADeletedUsersStillValidTokenIsRefused(): void
    {
        $middleware = $this->middleware(findById: fn () => null);

        $response = $middleware->process($this->requestWithToken($this->tokenFor(7, UserRole::Manager)), $this->handler());

        $this->assertUnauthorized($response, 'Invalid or expired token');
        $this->assertNull($this->handledRequest, 'the request never reaches the endpoint');
    }

    /**
     * @param array<non-empty-string, string> $headers
     */
    #[DataProvider('requestsWithoutAUsableToken')]
    public function testRefusesRequestsWithoutAUsableBearerToken(array $headers): void
    {
        $middleware = $this->middleware(findById: fn () => $this->fail('the database must not be asked'));

        $response = $middleware->process(new ServerRequest([], [], null, null, 'php://temp', $headers), $this->handler());

        $this->assertUnauthorized($response, 'Missing or invalid Authorization header');
        $this->assertNull($this->handledRequest);
    }

    /**
     * @return array<string, array{array<non-empty-string, string>}>
     */
    public static function requestsWithoutAUsableToken(): array
    {
        return [
            'no header' => [[]],
            'empty header' => [['Authorization' => '']],
            'a different scheme' => [['Authorization' => 'Basic dXNlcjpwYXNz']],
            'the scheme alone' => [['Authorization' => 'Bearer']],
            'the scheme with only spaces' => [['Authorization' => 'Bearer    ']],
        ];
    }

    public function testRefusesAGarbageToken(): void
    {
        $response = $this->middleware()->process($this->requestWithToken('not.a.jwt'), $this->handler());

        $this->assertUnauthorized($response, 'Invalid or expired token');
    }

    public function testRefusesAnExpiredToken(): void
    {
        $expired = (new AuthenticationService(self::SECRET, 'HS256', -60))
            ->createToken(new AuthenticatedUserModel(7, 'Jane', 'jane@example.com', UserRole::Employee));

        $response = $this->middleware()->process($this->requestWithToken($expired), $this->handler());

        $this->assertUnauthorized($response, 'Invalid or expired token');
    }

    public function testRefusesATokenSignedWithAnotherSecret(): void
    {
        $forged = (new AuthenticationService('some-other-secret-that-an-attacker-knows', 'HS256'))
            ->createToken(new AuthenticatedUserModel(7, 'Jane', 'jane@example.com', UserRole::Manager));

        $response = $this->middleware()->process($this->requestWithToken($forged), $this->handler());

        $this->assertUnauthorized($response, 'Invalid or expired token');
    }

    public function testRefusesATamperedToken(): void
    {
        [$header, $payload, $signature] = explode('.', $this->tokenFor(7, UserRole::Employee));
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($claims);
        $claims['role'] = 'manager';
        $forgedPayload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $response = $this->middleware()->process(
            $this->requestWithToken("$header.$forgedPayload.$signature"),
            $this->handler()
        );

        $this->assertUnauthorized($response, 'Invalid or expired token');
    }

    public function testAcceptsTheBearerSchemeInAnyCase(): void
    {
        $current = new AuthenticatedUserModel(7, 'Jane', 'jane@example.com', UserRole::Employee);
        $middleware = $this->middleware(findById: fn () => $current);

        $response = $middleware->process(
            new ServerRequest([], [], null, null, 'php://temp', ['Authorization' => 'bearer ' . $this->tokenFor(7, UserRole::Employee)]),
            $this->handler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    private function assertUnauthorized(ResponseInterface $response, string $message): void
    {
        $this->assertSame(401, $response->getStatusCode());
        /** @var array{status_code: int, reason_phrase: string} $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($message, $body['reason_phrase']);
    }

    /**
     * @param (callable(int): ?AuthenticatedUserModel)|null $findById
     */
    private function middleware(?callable $findById = null): AuthenticationMiddleware
    {
        $repository = $this->createMock(AuthenticationRepository::class);
        $repository->method('findById')->willReturnCallback($findById ?? fn () => null);

        return new AuthenticationMiddleware(new AuthenticationService(self::SECRET), $repository);
    }

    private function tokenFor(int $id, UserRole $role): string
    {
        return (new AuthenticationService(self::SECRET))
            ->createToken(new AuthenticatedUserModel($id, 'Jane', 'jane@example.com', $role));
    }

    private function requestWithToken(string $token): ServerRequest
    {
        return new ServerRequest([], [], null, null, 'php://temp', ['Authorization' => "Bearer $token"]);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class ($this) implements RequestHandlerInterface {
            public function __construct(private AuthenticationMiddlewareTest $test)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->recordHandledRequest($request);

                return new JsonResponse([], 200);
            }
        };
    }

    public function recordHandledRequest(ServerRequestInterface $request): void
    {
        $this->handledRequest = $request;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Authentication\Services;

use App\Domains\Authentication\Middleware\AuthenticationMiddleware;
use App\Domains\Authentication\Models\AuthenticatedUserModel;
use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Users\Models\UserRole;
use Laminas\Diactoros\ServerRequest;
use LogicException;
use PHPUnit\Framework\TestCase;

class AccessControlServiceTest extends TestCase
{
    public function testAnEmployeeIsRecognisedAsOne(): void
    {
        $request = $this->requestFrom(UserRole::Employee);
        $service = new AccessControlService();

        $this->assertTrue($service->isEmployeeFromRequest($request));
        $this->assertFalse($service->isManagerFromRequest($request));
    }

    public function testAManagerIsRecognisedAsOne(): void
    {
        $request = $this->requestFrom(UserRole::Manager);
        $service = new AccessControlService();

        $this->assertTrue($service->isManagerFromRequest($request));
        $this->assertFalse($service->isEmployeeFromRequest($request));
    }

    public function testReturnsTheUserThatTheMiddlewareAttached(): void
    {
        $user = new AuthenticatedUserModel(5, 'Jane', 'jane@example.com', UserRole::Manager);
        $request = (new ServerRequest())->withAttribute(AuthenticationMiddleware::ATTRIBUTE, $user);

        $this->assertSame($user, (new AccessControlService())->getAuthenticatedUserModelFromRequest($request));
    }

    public function testFailsClosedWhenARouteIsMissingTheMiddleware(): void
    {
        $this->expectException(LogicException::class);

        // No user attached: this must never be treated as "anonymous but allowed".
        (new AccessControlService())->isManagerFromRequest(new ServerRequest());
    }

    public function testIgnoresAnythingElseStoredUnderThatAttribute(): void
    {
        $request = (new ServerRequest())->withAttribute(AuthenticationMiddleware::ATTRIBUTE, ['role' => 'manager']);

        $this->expectException(LogicException::class);

        (new AccessControlService())->isManagerFromRequest($request);
    }

    private function requestFrom(UserRole $role): ServerRequest
    {
        return (new ServerRequest())->withAttribute(
            AuthenticationMiddleware::ATTRIBUTE,
            new AuthenticatedUserModel(1, 'Jane', 'jane@example.com', $role)
        );
    }
}

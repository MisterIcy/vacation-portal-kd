<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Controllers\VacationsController;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Services\CancelVacation;
use App\Domains\Vacations\Services\DecideVacation;
use App\Domains\Vacations\Services\ListVacations;
use App\Domains\Vacations\Services\RequestVacation;
use App\Shared\AccessDenied;
use App\Shared\ModelCollection;
use App\Shared\Principal;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The controller only translates HTTP to a use case call and back; the rules are tested with the
 * use cases (VacationUseCasesTest, RequestVacationTest) and the vacation request itself.
 */
class VacationsControllerTest extends TestCase
{
    private Principal $caller;
    /** @var ListVacations&MockObject */
    private ListVacations $listVacations;
    /** @var RequestVacation&MockObject */
    private RequestVacation $requestVacation;
    /** @var DecideVacation&MockObject */
    private DecideVacation $decideVacation;
    /** @var CancelVacation&MockObject */
    private CancelVacation $cancelVacation;
    private ServerRequest $request;

    protected function setUp(): void
    {
        $this->caller = Principal::employee(1);
        $this->listVacations = $this->createMock(ListVacations::class);
        $this->requestVacation = $this->createMock(RequestVacation::class);
        $this->decideVacation = $this->createMock(DecideVacation::class);
        $this->cancelVacation = $this->createMock(CancelVacation::class);
        $this->request = new ServerRequest();
    }

    public function testListReturnsWhatTheUseCaseReturnsForTheCaller(): void
    {
        $this->listVacations->expects($this->once())->method('__invoke')->with($this->caller)->willReturn(new ModelCollection());

        $response = $this->controller()->list($this->request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('[]', (string) $response->getBody());
    }

    public function testApproveCallsTheUseCaseWithTheCallerAndTheId(): void
    {
        $this->decideVacation->expects($this->once())->method('approve')->with($this->caller, 5)->willReturn($this->vacation());

        $response = $this->controller()->approve($this->request, ['id' => 5]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"reason":"Holiday"', (string) $response->getBody());
    }

    public function testRejectCallsTheUseCaseWithTheCallerAndTheId(): void
    {
        $this->decideVacation->expects($this->once())->method('reject')->with($this->caller, 6)->willReturn($this->vacation());

        $this->assertSame(200, $this->controller()->reject($this->request, ['id' => 6])->getStatusCode());
    }

    public function testCreateParsesTheBodyAndReturns201(): void
    {
        $this->requestVacation->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->caller,
                $this->callback(fn ($from) => $from instanceof DateTimeInterface && $from->format('Y-m-d') === '2026-07-01'),
                $this->callback(fn ($to) => $to instanceof DateTimeInterface && $to->format('Y-m-d') === '2026-07-03'),
                'Holiday',
            )
            ->willReturn($this->vacation());

        $response = $this->controller()->create($this->withBody('{"from":"2026-07-01","to":"2026-07-03","reason":"Holiday"}'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('"reason":"Holiday"', (string) $response->getBody());
    }

    public function testCreateRejectsAMalformedBodyBeforeCallingTheUseCase(): void
    {
        $this->requestVacation->expects($this->never())->method('__invoke');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'from' is required.");

        $this->controller()->create($this->withBody('{"to":"2026-07-03","reason":"Holiday"}'));
    }

    public function testCreateRejectsInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        $this->controller()->create($this->withBody('{not json'));
    }

    public function testDeleteCallsTheUseCaseAndReturns204(): void
    {
        $this->cancelVacation->expects($this->once())->method('__invoke')->with($this->caller, 7);

        $this->assertSame(204, $this->controller()->delete($this->request, ['id' => 7])->getStatusCode());
    }

    public function testARefusalFromAUseCaseIsNotSwallowed(): void
    {
        $this->decideVacation->method('approve')->willThrowException(AccessDenied::forRole());

        $this->expectException(AccessDenied::class);

        $this->controller()->approve($this->request, ['id' => 5]);
    }

    private function controller(): VacationsController
    {
        $accessControl = $this->createMock(AccessControlService::class);
        $accessControl->method('principalFromRequest')->willReturn($this->caller);

        return new VacationsController(
            $accessControl,
            $this->listVacations,
            $this->requestVacation,
            $this->decideVacation,
            $this->cancelVacation,
        );
    }

    private function withBody(string $json): ServerRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($json);

        return new ServerRequest([], [], null, null, $body);
    }

    private function vacation(): VacationRequest
    {
        return new VacationRequest(5, 1, null, new DateTime('2026-07-01'), new DateTime('2026-07-03'), 'Holiday');
    }
}

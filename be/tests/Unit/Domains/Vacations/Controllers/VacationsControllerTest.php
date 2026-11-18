<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Controllers;

use App\Domains\Authentication\Services\AccessControlService;
use App\Domains\Vacations\Controllers\VacationsController;
use App\Domains\Vacations\Models\EmployeeSummary;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Services\CancelVacation;
use App\Domains\Vacations\Services\DecideVacation;
use App\Domains\Vacations\Services\ListVacations;
use App\Domains\Vacations\Services\RequestVacation;
use App\Shared\AccessDenied;
use App\Shared\Events\EventDispatcher;
use App\Shared\Events\VacationApprovedEvent;
use App\Shared\Events\VacationRejectedEvent;
use App\Shared\Notifications\VacationDecision;
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
    /** @var EventDispatcher&MockObject */
    private EventDispatcher $events;
    private ServerRequest $request;

    protected function setUp(): void
    {
        $this->caller = Principal::employee(1);
        $this->listVacations = $this->createMock(ListVacations::class);
        $this->requestVacation = $this->createMock(RequestVacation::class);
        $this->decideVacation = $this->createMock(DecideVacation::class);
        $this->cancelVacation = $this->createMock(CancelVacation::class);
        $this->events = $this->createMock(EventDispatcher::class);
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

    // ---- Notifications ----------------------------------------------------------------------

    public function testApprovingDispatchesAnApprovedEventForTheEmployee(): void
    {
        $this->decideVacation->method('approve')->willReturn($this->decidedVacation());
        $this->events->expects($this->once())->method('dispatch')->with($this->callback(function ($event) {
            $n = $event instanceof VacationApprovedEvent ? $event->notification : null;

            return $n !== null
                && $n->decision === VacationDecision::Approved
                && $n->recipientId === 2
                && $n->recipientName === 'Ada Lovelace'
                && $n->recipientEmail === 'ada@example.com'
                && $n->vacationId === 5
                && $n->from->format('Y-m-d') === '2026-07-01'
                && $n->to->format('Y-m-d') === '2026-07-03'
                && $n->days === 3
                && $n->reason === 'Holiday'
                && $n->message === null;
        }));

        $this->assertSame(200, $this->controller()->approve($this->request, ['id' => 5])->getStatusCode());
    }

    public function testRejectingDispatchesARejectedEvent(): void
    {
        $this->decideVacation->method('reject')->willReturn($this->decidedVacation());
        $this->events->expects($this->once())->method('dispatch')->with($this->isInstanceOf(VacationRejectedEvent::class));

        $this->assertSame(200, $this->controller()->reject($this->request, ['id' => 5])->getStatusCode());
    }

    public function testTheManagersMessageTravelsInTheEvent(): void
    {
        $this->decideVacation->method('reject')->willReturn($this->decidedVacation());
        $this->events->expects($this->once())->method('dispatch')->with($this->callback(
            fn ($event) => $event instanceof VacationRejectedEvent && $event->notification->message === 'Team is short that week.'
        ));

        $this->controller()->reject($this->withBody('{"message":"Team is short that week."}'), ['id' => 5]);
    }

    public function testNothingIsDispatchedWhenTheDecisionFails(): void
    {
        $this->decideVacation->method('approve')->willThrowException(AccessDenied::forRole());
        $this->events->expects($this->never())->method('dispatch');

        $this->expectException(AccessDenied::class);

        $this->controller()->approve($this->request, ['id' => 5]);
    }

    public function testATooLongMessageIsRefusedBeforeAnythingIsDecided(): void
    {
        $this->decideVacation->expects($this->never())->method('approve');
        $this->events->expects($this->never())->method('dispatch');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'message' must be at most 500 characters.");

        $this->controller()->approve($this->withBody('{"message":"' . str_repeat('x', 501) . '"}'), ['id' => 5]);
    }

    public function testAMessageThatIsNotAStringIsRefusedBeforeAnythingIsDecided(): void
    {
        $this->decideVacation->expects($this->never())->method('reject');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'message' must be a string.");

        $this->controller()->reject($this->withBody('{"message":42}'), ['id' => 5]);
    }

    public function testARequestWithoutAnEmployeeAttachedIsDecidedButNobodyIsNotified(): void
    {
        $this->decideVacation->method('approve')->willReturn($this->vacation());
        $this->events->expects($this->never())->method('dispatch');

        $this->assertSame(200, $this->controller()->approve($this->request, ['id' => 5])->getStatusCode());
    }

    private function decidedVacation(): VacationRequest
    {
        return new VacationRequest(
            5,
            2,
            new EmployeeSummary(2, 'Ada Lovelace', 'ada@example.com', '1234567', 'employee'),
            new DateTime('2026-07-01'),
            new DateTime('2026-07-03'),
            'Holiday',
        );
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
            $this->events,
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

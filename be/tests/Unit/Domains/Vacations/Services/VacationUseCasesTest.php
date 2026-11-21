<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Models\StatusFilter;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Domains\Vacations\Services\CancelVacation;
use App\Domains\Vacations\Services\DecideVacation;
use App\Domains\Vacations\Services\ListVacations;
use App\Shared\AccessDenied;
use App\Shared\ModelCollection;
use App\Shared\NotFound;
use App\Shared\Principal;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * The rules about who may do what, and when, tested without HTTP or a database.
 */
class VacationUseCasesTest extends TestCase
{
    // ---- ListVacations ----------------------------------------------------------------------

    public function testAManagerSeesTheRequestsWaitingForADecision(): void
    {
        $pending = new ModelCollection();
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->once())->method('findRequests')->with(StatusFilter::Pending)->willReturn($pending);
        $repository->expects($this->never())->method('findUserRequests');

        $this->assertSame($pending, (new ListVacations($repository))(Principal::manager(1)));
    }

    public function testAManagerCanAskForTheHistoryOfDecidedRequests(): void
    {
        $decided = new ModelCollection();
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->once())->method('findRequests')->with(StatusFilter::Approved)->willReturn($decided);

        $this->assertSame($decided, (new ListVacations($repository))(Principal::manager(1), StatusFilter::Approved));
    }

    public function testAManagerCanAskForEveryRequest(): void
    {
        $everything = new ModelCollection();
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->once())->method('findRequests')->with(StatusFilter::All)->willReturn($everything);

        $this->assertSame($everything, (new ListVacations($repository))(Principal::manager(1), StatusFilter::All));
    }

    public function testAnEmployeeSeesAllOfTheirOwnRequestsByDefault(): void
    {
        $own = new ModelCollection();
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->once())->method('findUserRequests')->with(5, StatusFilter::All)->willReturn($own);
        $repository->expects($this->never())->method('findRequests');

        $this->assertSame($own, (new ListVacations($repository))(Principal::employee(5)));
    }

    public function testAnEmployeeCanNarrowTheirOwnRequestsByStatusButNeverSeeOthers(): void
    {
        $own = new ModelCollection();
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->once())->method('findUserRequests')->with(5, StatusFilter::Pending)->willReturn($own);
        $repository->expects($this->never())->method('findRequests');

        $this->assertSame($own, (new ListVacations($repository))(Principal::employee(5), StatusFilter::Pending));
    }

    // ---- DecideVacation ---------------------------------------------------------------------

    public function testAManagerApprovesAPendingRequest(): void
    {
        $pending = $this->vacation(VacationStatus::Pending);
        $stored = $pending->approve(1);
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->with(5)->willReturn($pending);
        $repository->expects($this->once())
            ->method('updateStatus')
            ->with(5, 1, VacationStatus::Approved)
            ->willReturn($stored);

        $result = (new DecideVacation($repository))->approve(Principal::manager(1), 5);

        $this->assertSame($stored, $result);
    }

    public function testAManagerRejectsAPendingRequest(): void
    {
        $pending = $this->vacation(VacationStatus::Pending);
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($pending);
        $repository->expects($this->once())
            ->method('updateStatus')
            ->with(5, 1, VacationStatus::Rejected)
            ->willReturn($pending->reject(1));

        (new DecideVacation($repository))->reject(Principal::manager(1), 5);
    }

    public function testAnEmployeeCannotDecideAndNothingIsTouched(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->never())->method($this->anything());

        $this->expectException(AccessDenied::class);

        (new DecideVacation($repository))->approve(Principal::employee(2), 5);
    }

    public function testAnEmployeeCannotRejectEither(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->never())->method($this->anything());

        $this->expectException(AccessDenied::class);

        (new DecideVacation($repository))->reject(Principal::employee(2), 5);
    }

    /**
     * A person promoted to manager while their own request is waiting must not decide it.
     */
    public function testAManagerCannotApproveTheirOwnRequestAndNothingIsWritten(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($this->vacation(VacationStatus::Pending, ownerId: 7));
        $repository->expects($this->never())->method('updateStatus');

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessage('You cannot decide on your own vacation request.');

        (new DecideVacation($repository))->approve(Principal::manager(7), 5);
    }

    public function testAManagerCannotRejectTheirOwnRequestEither(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($this->vacation(VacationStatus::Pending, ownerId: 7));
        $repository->expects($this->never())->method('updateStatus');

        $this->expectException(AccessDenied::class);

        (new DecideVacation($repository))->reject(Principal::manager(7), 5);
    }

    public function testAManagerStillDecidesOnSomeoneElsesRequest(): void
    {
        $pending = $this->vacation(VacationStatus::Pending, ownerId: 2);
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($pending);
        $repository->expects($this->once())->method('updateStatus')->willReturn($pending->approve(7));

        (new DecideVacation($repository))->approve(Principal::manager(7), 5);
    }

    public function testADecidedRequestCannotBeDecidedAgainAndIsNotWritten(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($this->vacation(VacationStatus::Approved));
        $repository->expects($this->never())->method('updateStatus');

        $this->expectException(VacationNotPending::class);
        $this->expectExceptionMessage('Only pending vacations can be updated.');

        (new DecideVacation($repository))->approve(Principal::manager(1), 5);
    }

    public function testDecidingAMissingRequestFails(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willThrowException(new NotFound('Vacation with ID 5 not found.'));

        $this->expectException(NotFound::class);

        (new DecideVacation($repository))->approve(Principal::manager(1), 5);
    }

    // ---- CancelVacation ---------------------------------------------------------------------

    public function testAnEmployeeCancelsTheirOwnPendingRequest(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->with(5)->willReturn($this->vacation(VacationStatus::Pending, ownerId: 2));
        $repository->expects($this->once())->method('delete')->with(5);

        (new CancelVacation($repository))(Principal::employee(2), 5);
    }

    public function testAManagerCannotCancelRequests(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->expects($this->never())->method($this->anything());

        $this->expectException(AccessDenied::class);

        (new CancelVacation($repository))(Principal::manager(1), 5);
    }

    public function testSomeoneElsesRequestIsReportedAsNotFoundAndIsNotDeleted(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($this->vacation(VacationStatus::Pending, ownerId: 99));
        $repository->expects($this->never())->method('delete');

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Vacation with ID 5 not found.');

        (new CancelVacation($repository))(Principal::employee(2), 5);
    }

    public function testADecidedRequestCannotBeCancelled(): void
    {
        $repository = $this->createMock(VacationRequestRepository::class);
        $repository->method('findById')->willReturn($this->vacation(VacationStatus::Approved, ownerId: 2));
        $repository->expects($this->never())->method('delete');

        $this->expectException(VacationNotPending::class);
        $this->expectExceptionMessage('Only pending vacations can be deleted.');

        (new CancelVacation($repository))(Principal::employee(2), 5);
    }

    private function vacation(VacationStatus $status, int $ownerId = 2): VacationRequest
    {
        return new VacationRequest(
            5,
            $ownerId,
            null,
            new DateTime('2026-07-01'),
            new DateTime('2026-07-05'),
            'Summer holiday',
            $status,
            $status === VacationStatus::Pending ? null : 1,
        );
    }
}

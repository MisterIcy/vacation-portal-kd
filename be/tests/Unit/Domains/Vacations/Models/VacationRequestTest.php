<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Models;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Models\DateRange;
use App\Domains\Vacations\Models\EmployeeSummary;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use DateInterval;
use DateTime;
use App\Shared\NotFound;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VacationRequestTest extends TestCase
{
    public function testValidConstructionMinimalFields(): void
    {
        $from = new DateTime()->add(new DateInterval('P1D'));
        $to = new DateTime()->add(new DateInterval('P2D'));

        $model = new VacationRequest(null, 1, null, $from, $to, 'Holiday');

        $this->assertNull($model->getId());
        $this->assertSame(1, $model->getUserId());
        $this->assertNull($model->getEmployee());
        $this->assertSame($from->format('Y-m-d'), $model->getFrom()->format('Y-m-d'));
        $this->assertSame($to->format('Y-m-d'), $model->getTo()->format('Y-m-d'));
        $this->assertSame('Holiday', $model->getReason());
        $this->assertSame(VacationStatus::Pending, $model->getStatus());
        $this->assertNull($model->getAuthorizedBy());
        $this->assertNull($model->getCreatedAt());
        $this->assertNull($model->getUpdatedAt());
    }

    public function testValidConstructionAllFields(): void
    {
        $user = new EmployeeSummary(2, 'Employee Name', 'employee@example.com', '1234567', 'employee');
        $from = new DateTime()->add(new DateInterval('P1D'));
        $to = new DateTime()->add(new DateInterval('P2D'));
        $today = new DateTime();
        $createdAt = $today;
        $updatedAt = $today;

        $model = new VacationRequest(5, 2, $user, $from, $to, 'Trip', VacationStatus::Approved, 99, $createdAt, $updatedAt);

        $this->assertSame(5, $model->getId());
        $this->assertSame(2, $model->getUserId());
        $this->assertEquals($user, $model->getEmployee());
        $this->assertSame($from->format('Y-m-d'), $model->getFrom()->format('Y-m-d'));
        $this->assertSame($to->format('Y-m-d'), $model->getTo()->format('Y-m-d'));
        $this->assertSame('Trip', $model->getReason());
        $this->assertSame(VacationStatus::Approved, $model->getStatus());
        $this->assertSame(99, $model->getAuthorizedBy());
        $this->assertEquals($createdAt, $model->getCreatedAt());
        $this->assertEquals($updatedAt, $model->getUpdatedAt());
    }

    public function testEmptyReasonThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason must not be empty.');

        $from = new DateTime()->add(new DateInterval('P1D'));
        $to = new DateTime()->add(new DateInterval('P2D'));
        new VacationRequest(null, 1, null, $from, $to, '   ');
    }

    public function testFromAfterToThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "from" date must be earlier than the "to" date.');

        $from = new DateTime()->add(new DateInterval('P2D'));
        $to = new DateTime()->add(new DateInterval('P1D'));
        new VacationRequest(null, 1, null, $from, $to, 'Trip');
    }

    public function testDurationExceedsMaxThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vacation duration cannot be more than 60 days.');

        $from = new DateTime()->add(new DateInterval('P1D'));
        $to = new DateTime()->add(new DateInterval('P'.(DateRange::MAX_DAYS + 2).'D'));
        new VacationRequest(null, 1, null, $from, $to, 'Trip');
    }

    public function testToArrayAndJsonSerialize(): void
    {
        $user = new EmployeeSummary(2, 'Employee Name', 'employee@example.com', '1234567', 'employee');
        $from = new DateTime()->add(new DateInterval('P1D'));
        $to = new DateTime()->add(new DateInterval('P2D'));
        $today = new DateTime();
        $createdAt = $today;
        $updatedAt = $today;

        $model = new VacationRequest(5, 2, $user, $from, $to, 'Trip', VacationStatus::Approved, 99, $createdAt, $updatedAt);

        $expected = [
            'id' => 5,
            'user_id' => 2,
            'user' => [
                'id' => 2,
                'name' => 'Employee Name',
                'email' => 'employee@example.com',
                'employee_code' => '1234567',
                'role' => 'employee',
            ],
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'days' => 2,
            'reason' => 'Trip',
            'status' => VacationStatus::Approved->value,
            'authorized_by' => 99,
            'created_at' => $createdAt->format(DATE_ATOM),
            'updated_at' => $updatedAt->format(DATE_ATOM),
        ];
        $this->assertSame($expected, $model->toArray());
        $this->assertSame($expected, $model->jsonSerialize());
    }

    /**
     * "Total days" counts both the first and the last day.
     */
    #[DataProvider('dayCounts')]
    public function testDaysCountsBothEndsInclusively(string $from, string $to, int $expectedDays): void
    {
        $model = new VacationRequest(null, 1, null, new DateTime($from), new DateTime($to), 'Trip');

        $this->assertSame($expectedDays, $model->getDays());
        $this->assertSame($expectedDays, $model->toArray()['days']);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function dayCounts(): array
    {
        return [
            'a single day' => ['2026-07-01', '2026-07-01', 1],
            'two consecutive days' => ['2026-07-01', '2026-07-02', 2],
            'across a month boundary' => ['2026-01-30', '2026-02-02', 4],
            'across a leap day' => ['2028-02-28', '2028-03-01', 3],
            'the longest allowed range' => ['2026-01-01', '2026-03-01', 60],
        ];
    }

    public function testAManagerCanApproveAPendingRequest(): void
    {
        $pending = $this->requestWith(VacationStatus::Pending);

        $approved = $pending->approve(7);

        $this->assertSame(VacationStatus::Approved, $approved->getStatus());
        $this->assertSame(7, $approved->getAuthorizedBy());
        $this->assertSame($pending->getId(), $approved->getId());
        $this->assertSame($pending->getReason(), $approved->getReason());
        $this->assertSame($pending->getDays(), $approved->getDays());
    }

    public function testAManagerCanRejectAPendingRequest(): void
    {
        $rejected = $this->requestWith(VacationStatus::Pending)->reject(7);

        $this->assertSame(VacationStatus::Rejected, $rejected->getStatus());
        $this->assertSame(7, $rejected->getAuthorizedBy());
    }

    public function testDecidingDoesNotChangeTheOriginalRequest(): void
    {
        $pending = $this->requestWith(VacationStatus::Pending);

        $pending->approve(7);

        $this->assertSame(VacationStatus::Pending, $pending->getStatus());
        $this->assertNull($pending->getAuthorizedBy());
    }

    #[DataProvider('decidedStatuses')]
    public function testADecidedRequestCannotBeDecidedAgain(VacationStatus $status): void
    {
        $decided = $this->requestWith($status);

        foreach (['approve', 'reject'] as $decision) {
            try {
                $decided->$decision(7);
                $this->fail("A $status->value request must not be able to $decision");
            } catch (VacationNotPending $e) {
                $this->assertSame('Only pending vacations can be updated.', $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, array{VacationStatus}>
     */
    public static function decidedStatuses(): array
    {
        return ['approved' => [VacationStatus::Approved], 'rejected' => [VacationStatus::Rejected]];
    }

    public function testTheOwnerCanCancelAPendingRequest(): void
    {
        $this->requestWith(VacationStatus::Pending, ownerId: 2)->cancel(2);

        $this->addToAssertionCount(1); // cancelling a valid request throws nothing
    }

    public function testSomeoneElseCannotCancelARequestAndIsNotToldItExists(): void
    {
        $request = $this->requestWith(VacationStatus::Pending, ownerId: 2);

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Vacation with ID 10 not found.');

        $request->cancel(99);
    }

    #[DataProvider('decidedStatuses')]
    public function testADecidedRequestCannotBeCancelled(VacationStatus $status): void
    {
        $request = $this->requestWith($status, ownerId: 2);

        $this->expectException(VacationNotPending::class);
        $this->expectExceptionMessage('Only pending vacations can be deleted.');

        $request->cancel(2);
    }

    public function testOwnershipIsCheckedBeforeStatusSoOthersLearnNothing(): void
    {
        $approved = $this->requestWith(VacationStatus::Approved, ownerId: 2);

        $this->expectExceptionMessage('Vacation with ID 10 not found.');

        $approved->cancel(99);
    }

    private function requestWith(VacationStatus $status, int $ownerId = 2): VacationRequest
    {
        return new VacationRequest(
            10,
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

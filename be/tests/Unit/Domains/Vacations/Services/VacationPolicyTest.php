<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationOverlap;
use App\Domains\Vacations\Models\DateRange;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Domains\Vacations\Services\VacationPolicy;
use App\Shared\Clock;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VacationPolicyTest extends TestCase
{
    private const string TODAY = '2026-07-10';

    public function testAVacationStartingTodayIsAllowed(): void
    {
        $this->policy(overlaps: false)->assertCanBeRequested($this->request('2026-07-10', '2026-07-12'));

        $this->addToAssertionCount(1);
    }

    public function testAVacationStartingInTheFutureIsAllowed(): void
    {
        $this->policy(overlaps: false)->assertCanBeRequested($this->request('2026-07-11', '2026-07-11'));

        $this->addToAssertionCount(1);
    }

    public function testAVacationStartingYesterdayIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A vacation cannot start in the past.');

        $this->policy(overlaps: false)->assertCanBeRequested($this->request('2026-07-09', '2026-07-12'));
    }

    public function testAVacationThatStartedInThePastEvenIfItEndsInTheFutureIsRejected(): void
    {
        $this->expectExceptionMessage('A vacation cannot start in the past.');

        $this->policy(overlaps: false)->assertCanBeRequested($this->request('2026-07-01', '2026-07-20'));
    }

    public function testARequestThatOverlapsAnotherIsRejected(): void
    {
        $this->expectException(VacationOverlap::class);
        $this->expectExceptionMessage('overlap with another pending or approved vacation request');

        $this->policy(overlaps: true)->assertCanBeRequested($this->request('2026-07-20', '2026-07-25'));
    }

    public function testTheOverlapCheckIsForTheSameEmployeeAndTheSameDays(): void
    {
        $repository = $this->createMock(VacationsRepository::class);
        $repository->expects($this->once())
            ->method('hasOverlap')
            ->with(
                42,
                $this->callback(fn (DateRange $range) => $range->from()->format('Y-m-d') === '2026-07-20'
                    && $range->to()->format('Y-m-d') === '2026-07-25')
            )
            ->willReturn(false);

        (new VacationPolicy($repository, $this->clock()))
            ->assertCanBeRequested($this->request('2026-07-20', '2026-07-25', employeeId: 42));
    }

    public function testThePastDateRuleIsCheckedWithoutTouchingTheDatabase(): void
    {
        $repository = $this->createMock(VacationsRepository::class);
        $repository->expects($this->never())->method('hasOverlap');

        $this->expectException(InvalidArgumentException::class);

        (new VacationPolicy($repository, $this->clock()))->assertCanBeRequested($this->request('2026-06-01', '2026-06-02'));
    }

    private function policy(bool $overlaps): VacationPolicy
    {
        $repository = $this->createMock(VacationsRepository::class);
        $repository->method('hasOverlap')->willReturn($overlaps);

        return new VacationPolicy($repository, $this->clock());
    }

    private function clock(): Clock
    {
        return new class (self::TODAY) implements Clock {
            public function __construct(private string $today)
            {
            }

            public function today(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->today, new DateTimeZone('UTC'));
            }
        };
    }

    private function request(string $from, string $to, int $employeeId = 2): VacationRequest
    {
        return new VacationRequest(null, $employeeId, null, new DateTime($from), new DateTime($to), 'Trip');
    }
}

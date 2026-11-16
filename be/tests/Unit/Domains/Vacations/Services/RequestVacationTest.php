<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationOverlap;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Domains\Vacations\Services\RequestVacation;
use App\Domains\Vacations\Services\VacationPolicy;
use App\Shared\Database;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RequestVacationTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    public function testLocksTheEmployeeThenChecksTheRulesThenSavesAllInOneTransaction(): void
    {
        $created = new VacationRequest(9, 2, null, new DateTime('2026-07-20'), new DateTime('2026-07-22'), 'Trip');

        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('transactional')->willReturnCallback(function (callable $work) {
            $this->calls[] = 'begin';
            $result = $work();
            $this->calls[] = 'commit';

            return $result;
        });

        $repository = $this->createMock(VacationsRepository::class);
        $repository->method('lockEmployee')->willReturnCallback(function (int $id): void {
            $this->calls[] = "lock $id";
        });
        $repository->method('create')->willReturnCallback(function () use ($created): VacationRequest {
            $this->calls[] = 'create';

            return $created;
        });

        $policy = $this->createMock(VacationPolicy::class);
        $policy->method('assertCanBeRequested')->willReturnCallback(function (): void {
            $this->calls[] = 'check rules';
        });

        $result = (new RequestVacation($database, $repository, $policy))(
            2,
            new DateTime('2026-07-20'),
            new DateTime('2026-07-22'),
            'Trip'
        );

        $this->assertSame($created, $result);
        $this->assertSame(['begin', 'lock 2', 'check rules', 'create', 'commit'], $this->calls);
    }

    public function testNothingIsSavedWhenARuleIsBroken(): void
    {
        $database = $this->createMock(Database::class);
        $database->method('transactional')->willReturnCallback(fn (callable $work) => $work());

        $repository = $this->createMock(VacationsRepository::class);
        $repository->expects($this->once())->method('lockEmployee');
        $repository->expects($this->never())->method('create');

        $policy = $this->createMock(VacationPolicy::class);
        $policy->method('assertCanBeRequested')->willThrowException(VacationOverlap::withExistingRequest());

        $this->expectException(VacationOverlap::class);

        (new RequestVacation($database, $repository, $policy))(2, new DateTime('2026-07-20'), new DateTime('2026-07-22'), 'Trip');
    }

    public function testAnInvalidRequestIsRefusedBeforeTheDatabaseIsInvolved(): void
    {
        $database = $this->createMock(Database::class);
        $database->expects($this->never())->method('transactional');
        $repository = $this->createMock(VacationsRepository::class);
        $repository->expects($this->never())->method('lockEmployee');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "from" date must be earlier than the "to" date.');

        (new RequestVacation($database, $repository, $this->createMock(VacationPolicy::class)))(
            2,
            new DateTime('2026-07-22'),
            new DateTime('2026-07-20'),
            'Trip'
        );
    }
}

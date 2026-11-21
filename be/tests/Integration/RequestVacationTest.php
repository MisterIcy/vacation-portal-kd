<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domains\Vacations\Exceptions\VacationOverlap;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Domains\Vacations\Services\RequestVacation;
use App\Domains\Vacations\Services\VacationPolicy;
use App\Shared\Clock;
use App\Shared\NotFound;
use App\Shared\Principal;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The use case with the real repository and a real transaction, which is where the lock on the
 * employee and the overlap check have to work together.
 */
class RequestVacationTest extends DatabaseTestCase
{
    private RequestVacation $request;
    private int $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $clock = new class () implements Clock {
            public function today(): DateTimeImmutable
            {
                return new DateTimeImmutable('2030-06-01');
            }
        };
        $vacations = new VacationsRepository($this->db);
        $this->request = new RequestVacation($this->db, $vacations, new VacationPolicy($vacations, $clock));
        $this->employee = $this->insertUser();
    }

    public function testARequestIsStoredAsPending(): void
    {
        $created = ($this->request)(Principal::employee($this->employee), new DateTime('2030-07-01'), new DateTime('2030-07-05'), 'Holiday');

        $row = $this->vacationRow((int) $created->getId());
        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
        $this->assertSame($this->employee, $this->intColumn($row, 'user_id'));
    }

    public function testASecondRequestOverlappingTheFirstIsRefusedAndNothingIsStored(): void
    {
        ($this->request)(Principal::employee($this->employee), new DateTime('2030-07-01'), new DateTime('2030-07-05'), 'First');

        try {
            ($this->request)(Principal::employee($this->employee), new DateTime('2030-07-05'), new DateTime('2030-07-08'), 'Second');
            $this->fail('The overlap should be refused.');
        } catch (VacationOverlap) {
        }

        $this->assertSame(1, $this->countRows('vacations'));
    }

    public function testTheDayAfterAnExistingRequestIsFree(): void
    {
        ($this->request)(Principal::employee($this->employee), new DateTime('2030-07-01'), new DateTime('2030-07-05'), 'First');
        ($this->request)(Principal::employee($this->employee), new DateTime('2030-07-06'), new DateTime('2030-07-08'), 'Second');

        $this->assertSame(2, $this->countRows('vacations'));
    }

    public function testARequestStartingInThePastIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ($this->request)(Principal::employee($this->employee), new DateTime('2030-05-30'), new DateTime('2030-06-02'), 'Backdated');
    }

    public function testAnEmployeeWhoNoLongerExistsCannotRequest(): void
    {
        $this->expectException(NotFound::class);

        ($this->request)(Principal::employee(999999), new DateTime('2030-07-01'), new DateTime('2030-07-02'), 'Ghost');
    }
}

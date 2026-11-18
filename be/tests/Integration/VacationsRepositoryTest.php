<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Models\DateRange;
use App\Domains\Vacations\Models\StatusFilter;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Shared\DatabaseErrors;
use App\Shared\ModelCollection;
use App\Shared\NotFound;
use DateTime;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;

class VacationsRepositoryTest extends DatabaseTestCase
{
    private VacationsRepository $vacations;
    private int $employee;
    private int $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vacations = new VacationsRepository($this->db);
        $this->employee = $this->insertUser(['name' => 'Ada Employee']);
        $this->manager = $this->insertUser(['name' => 'Grace Manager', 'role' => 'manager']);
    }

    /**
     * What the API would send for a list of requests.
     *
     * @return list<array<string, mixed>>
     */
    private function listOf(ModelCollection $collection): array
    {
        /** @var list<array<string, mixed>> $list */
        $list = $collection->jsonSerialize();

        return $list;
    }

    private function range(string $from, string $to): DateRange
    {
        return new DateRange(new DateTimeImmutable($from), new DateTimeImmutable($to));
    }

    // ---- Writing -------------------------------------------------------------------------------

    public function testACreatedRequestIsPendingAndHasItsTimestamps(): void
    {
        $created = $this->vacations->create(
            new VacationRequest(null, $this->employee, null, new DateTime('2030-07-01'), new DateTime('2030-07-10'), 'Summer'),
        );

        $this->assertNotNull($created->getId());
        $this->assertSame(VacationStatus::Pending, $created->getStatus());
        $this->assertNotNull($created->getCreatedAt());

        $row = $this->vacationRow($created->getId());
        $this->assertNotNull($row);
        $this->assertSame('2030-07-01', $row['from']);
        $this->assertSame('2030-07-10', $row['to']);
        $this->assertNull($row['authorized_by']);
    }

    public function testAFoundRequestCarriesItsEmployee(): void
    {
        $id = $this->insertVacation($this->employee, '2030-07-01', '2030-07-03');

        $found = $this->vacations->findById($id);

        $this->assertSame('Ada Employee', $found->getEmployee()?->getName());
        $this->assertSame(3, $found->getDays());
    }

    public function testAMissingRequestIsNotFound(): void
    {
        $this->expectException(NotFound::class);

        $this->vacations->findById(999999);
    }

    // ---- Deciding: the conditional write that keeps two managers from both winning ------------

    public function testAPendingRequestCanBeDecidedAndRecordsWho(): void
    {
        $id = $this->insertVacation($this->employee, '2030-07-01', '2030-07-03');

        $decided = $this->vacations->updateStatus($id, $this->manager, VacationStatus::Approved);

        $this->assertSame(VacationStatus::Approved, $decided->getStatus());
        $this->assertSame($this->manager, $decided->getAuthorizedBy());
    }

    public function testADecidedRequestCannotBeDecidedAgainAndStaysAsItWas(): void
    {
        $id = $this->insertVacation($this->employee, '2030-07-01', '2030-07-03');
        $this->vacations->updateStatus($id, $this->manager, VacationStatus::Approved);
        $other = $this->insertUser(['role' => 'manager']);

        $this->assertRefusedWith(
            VacationNotPending::class,
            fn () => $this->vacations->updateStatus($id, $other, VacationStatus::Rejected),
        );

        $row = $this->vacationRow($id);
        $this->assertNotNull($row);
        $this->assertSame('approved', $row['status']);
        $this->assertSame($this->manager, $this->intColumn($row, 'authorized_by'));
    }

    public function testOnlyAPendingRequestCanBeDeleted(): void
    {
        $pending = $this->insertVacation($this->employee, '2030-07-01', '2030-07-03');
        $approved = $this->insertVacation($this->employee, '2030-08-01', '2030-08-03', ['status' => 'approved']);

        $this->vacations->delete($pending);
        $this->assertNull($this->vacationRow($pending));

        $this->assertRefusedWith(VacationNotPending::class, fn () => $this->vacations->delete($approved));
        $this->assertNotNull($this->vacationRow($approved));
    }

    // ---- Overlap: both ends of a request are included ------------------------------------------

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function overlapCases(): array
    {
        // The existing request is 10 to 15 July 2030.
        return [
            'ends the day before it starts' => ['2030-07-05', '2030-07-09', false],
            'starts the day after it ends' => ['2030-07-16', '2030-07-20', false],
            'ends on the day it starts' => ['2030-07-05', '2030-07-10', true],
            'starts on the day it ends' => ['2030-07-15', '2030-07-20', true],
            'sits inside it' => ['2030-07-12', '2030-07-13', true],
            'contains it' => ['2030-07-01', '2030-07-31', true],
            'is the same days' => ['2030-07-10', '2030-07-15', true],
            'a single day on its first day' => ['2030-07-10', '2030-07-10', true],
            'a single day just before it' => ['2030-07-09', '2030-07-09', false],
        ];
    }

    #[DataProvider('overlapCases')]
    public function testOverlapWithAnExistingRequest(string $from, string $to, bool $overlaps): void
    {
        $this->insertVacation($this->employee, '2030-07-10', '2030-07-15');

        $this->assertSame($overlaps, $this->vacations->hasOverlap($this->employee, $this->range($from, $to)));
    }

    public function testAnApprovedRequestBlocksTheSameDays(): void
    {
        $this->insertVacation($this->employee, '2030-07-10', '2030-07-15', ['status' => 'approved']);

        $this->assertTrue($this->vacations->hasOverlap($this->employee, $this->range('2030-07-12', '2030-07-13')));
    }

    public function testARejectedRequestFreesItsDays(): void
    {
        $this->insertVacation($this->employee, '2030-07-10', '2030-07-15', ['status' => 'rejected']);

        $this->assertFalse($this->vacations->hasOverlap($this->employee, $this->range('2030-07-12', '2030-07-13')));
    }

    public function testAnotherEmployeesRequestBlocksNothing(): void
    {
        $this->insertVacation($this->insertUser(), '2030-07-10', '2030-07-15');

        $this->assertFalse($this->vacations->hasOverlap($this->employee, $this->range('2030-07-12', '2030-07-13')));
    }

    // ---- Reading -------------------------------------------------------------------------------

    public function testTheManagersQueueHoldsPendingRequestsEarliestFirstWithWhoAsked(): void
    {
        $this->insertVacation($this->employee, '2030-09-01', '2030-09-02');
        $this->insertVacation($this->employee, '2030-07-01', '2030-07-02');
        $this->insertVacation($this->employee, '2030-08-01', '2030-08-02', ['status' => 'approved']);

        $queue = $this->listOf($this->vacations->findRequests(StatusFilter::Pending));

        $this->assertSame(['2030-07-01', '2030-09-01'], array_column($queue, 'from'));
        $asker = $queue[0]['user'];
        $this->assertIsArray($asker);
        $this->assertSame('Ada Employee', $asker['name']);
    }

    public function testHistoryIsNewestFirstAndFiltersByStatus(): void
    {
        $this->insertVacation($this->employee, '2030-07-01', '2030-07-02', ['status' => 'approved']);
        $this->insertVacation($this->employee, '2030-09-01', '2030-09-02', ['status' => 'approved']);
        $this->insertVacation($this->employee, '2030-08-01', '2030-08-02', ['status' => 'rejected']);
        $this->insertVacation($this->employee, '2030-10-01', '2030-10-02');

        $approved = $this->listOf($this->vacations->findRequests(StatusFilter::Approved));
        $all = $this->listOf($this->vacations->findRequests(StatusFilter::All));

        $this->assertSame(['2030-09-01', '2030-07-01'], array_column($approved, 'from'));
        $this->assertSame(['2030-10-01', '2030-09-01', '2030-08-01', '2030-07-01'], array_column($all, 'from'));
    }

    public function testAnEmployeeSeesOnlyTheirOwnRequests(): void
    {
        $mine = $this->insertVacation($this->employee, '2030-07-01', '2030-07-02');
        $this->insertVacation($this->insertUser(), '2030-07-01', '2030-07-02');

        $own = $this->listOf($this->vacations->findUserRequests($this->employee));

        $this->assertSame([$mine], array_column($own, 'id'));
        $this->assertNull($own[0]['user']);
    }

    public function testAnEmployeesOwnRequestsCanBeNarrowedByStatus(): void
    {
        $this->insertVacation($this->employee, '2030-07-01', '2030-07-02');
        $approved = $this->insertVacation($this->employee, '2030-08-01', '2030-08-02', ['status' => 'approved']);

        $found = $this->listOf($this->vacations->findUserRequests($this->employee, StatusFilter::Approved));

        $this->assertSame([$approved], array_column($found, 'id'));
    }

    // ---- Locking and the rules the database itself enforces ------------------------------------

    public function testLockingAnEmployeeThatDoesNotExistIsNotFound(): void
    {
        $this->expectException(NotFound::class);

        $this->vacations->lockEmployee(999999);
    }

    public function testLockingAnExistingEmployeeSucceedsInsideATransaction(): void
    {
        $this->vacations->lockEmployee($this->employee);

        $this->addToAssertionCount(1);
    }

    public function testTheDatabaseRefusesARequestThatEndsBeforeItStarts(): void
    {
        try {
            $this->insertVacation($this->employee, '2030-07-10', '2030-07-01');
            $this->fail('The CHECK constraint should refuse this.');
        } catch (PDOException $e) {
            $this->assertTrue(DatabaseErrors::isCheckViolation($e), $e->getMessage());
        }
    }

    public function testTheDatabaseRefusesARequestForAnEmployeeWhoDoesNotExist(): void
    {
        try {
            $this->insertVacation(999999, '2030-07-01', '2030-07-02');
            $this->fail('The foreign key should refuse this.');
        } catch (PDOException $e) {
            $this->assertSame(1452, DatabaseErrors::driverErrorCode($e));
        }
    }

    public function testTheDatabaseRefusesAStatusItDoesNotKnow(): void
    {
        $this->expectException(PDOException::class);

        $this->insertVacation($this->employee, '2030-07-01', '2030-07-02', ['status' => 'maybe']);
    }
}

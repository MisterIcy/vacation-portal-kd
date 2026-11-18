<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domains\Users\Models\UserModel;
use App\Domains\Users\Models\UserRole;
use App\Domains\Users\Repositories\UsersRepository;
use App\Shared\Conflict;
use App\Shared\DatabaseErrors;
use App\Shared\NotFound;
use App\Shared\Ports\PasswordHasher;
use PDOException;

class UsersRepositoryTest extends DatabaseTestCase
{
    private UsersRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        // Hashing is not what is tested here, and Argon2id on purpose takes a while.
        $hasher = new class () implements PasswordHasher {
            public function hash(string $plain): string
            {
                return 'hashed:' . $plain;
            }

            public function verify(string $plain, string $hash): bool
            {
                return $hash === 'hashed:' . $plain;
            }
        };
        $this->users = new UsersRepository($this->db, $hasher);
    }

    private function newUser(string $email = 'ada@example.com', string $code = '1234567'): UserModel
    {
        return new UserModel(null, $email, 'Password123', 'Ada Lovelace', $code, UserRole::Employee);
    }

    public function testACreatedUserIsStoredWithItsTimestampsAndAHashedPassword(): void
    {
        $created = $this->users->create($this->newUser());

        $this->assertNotNull($created->getId());
        $this->assertNotNull($created->getCreatedAt());

        $row = $this->db->query('SELECT * FROM users WHERE id = :id', ['id' => $created->getId()])[0];
        $this->assertSame('ada@example.com', $row['email']);
        $this->assertSame('hashed:Password123', $row['password']);
        $this->assertSame('employee', $row['role']);
    }

    public function testALeadingZeroInTheEmployeeCodeSurvives(): void
    {
        $created = $this->users->create($this->newUser('zero@example.com', '0000042'));

        $this->assertSame('0000042', $this->users->findById((int) $created->getId())->getEmployeeCode());
    }

    public function testADuplicateEmailIsAConflictAndStoresNothing(): void
    {
        $this->users->create($this->newUser('ada@example.com', '1111111'));

        try {
            $this->users->create($this->newUser('ada@example.com', '2222222'));
            $this->fail('A duplicate email should be refused.');
        } catch (Conflict $e) {
            $this->assertStringContainsString('already in use', $e->getMessage());
        }

        $this->assertSame(1, $this->countRows('users'));
    }

    public function testEmailsAreUniqueWhateverTheirCase(): void
    {
        $this->users->create($this->newUser('ada@example.com', '1111111'));

        $this->expectException(Conflict::class);

        $this->users->create($this->newUser('ADA@Example.com', '2222222'));
    }

    public function testADuplicateEmployeeCodeIsAConflict(): void
    {
        $this->users->create($this->newUser('one@example.com', '1234567'));

        $this->expectException(Conflict::class);

        $this->users->create($this->newUser('two@example.com', '1234567'));
    }

    public function testChangingAnEmailToOneInUseIsAConflictAndChangesNothing(): void
    {
        $this->users->create($this->newUser('taken@example.com', '1111111'));
        $second = $this->users->create($this->newUser('mine@example.com', '2222222'));

        try {
            $this->users->update(new UserModel($second->getId(), 'taken@example.com', null, 'Ada Lovelace', '2222222', UserRole::Employee));
            $this->fail('An email in use should be refused.');
        } catch (Conflict) {
        }

        $this->assertSame('mine@example.com', $this->users->findById((int) $second->getId())->getEmail());
    }

    public function testAMissingUserIsNotFound(): void
    {
        $this->expectException(NotFound::class);

        $this->users->findById(999999);
    }

    public function testDeletingAMissingUserIsNotFound(): void
    {
        $this->expectException(NotFound::class);

        $this->users->delete(999999);
    }

    public function testTheDatabaseRefusesAnEmployeeCodeThatIsNotSevenDigits(): void
    {
        // Too short or not digits: the CHECK constraint. Too long: the column is only seven characters wide.
        foreach (['123456' => 3819, 'abc1234' => 3819, '12 4567' => 3819, '12345678' => 1406] as $code => $error) {
            try {
                $this->insertUser(['employee_code' => (string) $code]);
                $this->fail("The code '$code' should be refused.");
            } catch (PDOException $e) {
                $this->assertSame($error, DatabaseErrors::driverErrorCode($e), "'$code': " . $e->getMessage());
            }
        }
    }

    public function testTheDatabaseRefusesAnUnknownRole(): void
    {
        $this->expectException(PDOException::class);

        $this->insertUser(['role' => 'director']);
    }

    public function testDeletingAUserDeletesTheirRequests(): void
    {
        $employee = $this->insertUser();
        $this->insertVacation($employee, '2030-07-01', '2030-07-03');
        $this->insertVacation($employee, '2030-08-01', '2030-08-03', ['status' => 'approved']);

        $this->users->delete($employee);

        $this->assertSame(0, $this->countRows('vacations'));
    }

    public function testDeletingAManagerKeepsTheRequestsTheyDecidedAndForgetsWhoDecidedThem(): void
    {
        $manager = $this->insertUser(['role' => 'manager']);
        $employee = $this->insertUser();
        $decided = $this->insertVacation($employee, '2030-07-01', '2030-07-03', ['status' => 'approved', 'authorized_by' => $manager]);

        $this->users->delete($manager);

        $row = $this->vacationRow($decided);
        $this->assertNotNull($row);
        $this->assertSame('approved', $row['status']);
        $this->assertNull($row['authorized_by']);
    }
}

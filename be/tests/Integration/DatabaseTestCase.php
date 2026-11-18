<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Shared\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that talk to a real MySQL, because some things cannot be shown with a mocked
 * Database: constraints, cascades, collations, strict mode, what a query really returns.
 *
 * The database is configured with TEST_DB_HOST, TEST_DB_DATABASE, TEST_DB_USERNAME, TEST_DB_PASSWORD
 * (and optionally TEST_DB_PORT) and needs the migrations applied (`composer db:migrate:test`).
 * `./run.local check-integration` starts a throwaway one and does all of that.
 *
 * Every test runs inside a transaction that is rolled back afterwards, so tests do not see each
 * other's rows and nothing is left behind. The code under test joins that transaction.
 * Where nothing is configured the tests are skipped, unless REQUIRE_INTEGRATION_TESTS is set (as it
 * should be in CI), when a missing database is a failure rather than a silent pass.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** Makes the emails and employee codes of the users a test builds different from each other. */
    private static int $sequence = 0;

    protected PDO $pdo;
    protected Database $db;

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST');
        if ($host === false || $host === '') {
            $this->databaseNotConfigured('TEST_DB_HOST is not set, so there is no database to run against.');
        }

        $name = $this->setting('TEST_DB_DATABASE', 'vacation_test');
        // These tests write and roll back; make it impossible to point them at real data by mistake.
        if (!str_contains($name, 'test')) {
            $this->fail("Refusing to run against the database '$name': the name of a test database must contain 'test'.");
        }

        try {
            $this->pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $this->setting('TEST_DB_PORT', '3306') . ';dbname=' . $name . ';charset=utf8mb4',
                $this->setting('TEST_DB_USERNAME', 'vacation_test'),
                $this->setting('TEST_DB_PASSWORD', ''),
                [PDO::MYSQL_ATTR_INIT_COMMAND => Database::SESSION_INIT_SQL],
            );
        } catch (PDOException $e) {
            $this->fail('Cannot connect to the test database: ' . $e->getMessage());
        }

        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $applied = $this->pdo->query("SHOW TABLES LIKE 'vacations'");
        if ($applied === false || $applied->fetchColumn() === false) {
            $this->fail('The test database has no schema. Run "composer db:migrate:test" first.');
        }

        $this->db = new Database([], $this->pdo);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function databaseNotConfigured(string $reason): never
    {
        $required = getenv('REQUIRE_INTEGRATION_TESTS');
        if ($required !== false && $required !== '') {
            $this->fail($reason . ' (REQUIRE_INTEGRATION_TESTS is set.)');
        }

        $this->markTestSkipped($reason . ' Run them with "./run.local check-integration".');
    }

    private function setting(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    // ---- Builders: rows written directly, so a test about one thing does not depend on another ----

    /**
     * @param array<string, string> $overrides columns of the users table
     * @return int the new user's id
     */
    protected function insertUser(array $overrides = []): int
    {
        self::$sequence++;

        $row = $overrides + [
            'email' => 'person' . self::$sequence . '@example.com',
            'password' => 'not-a-real-hash',
            'name' => 'Person ' . self::$sequence,
            'employee_code' => str_pad((string) self::$sequence, 7, '0', STR_PAD_LEFT),
            'role' => 'employee',
        ];

        return $this->insert('users', $row);
    }

    /**
     * @param array<string, string|int|null> $overrides columns of the vacations table
     * @return int the new request's id
     */
    protected function insertVacation(int $userId, string $from, string $to, array $overrides = []): int
    {
        return $this->insert('vacations', $overrides + [
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
            'reason' => 'Holiday',
            'status' => 'pending',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function vacationRow(int $id): ?array
    {
        $rows = $this->db->query('SELECT * FROM vacations WHERE id = :id', ['id' => $id]);

        return $rows[0] ?? null;
    }

    /**
     * A numeric column of a row read from the database, as a number (PDO gives back what MySQL sent).
     *
     * @param array<string, mixed> $row
     */
    protected function intColumn(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        $this->assertIsNumeric($value, $column);

        return (int) $value;
    }

    /**
     * Fails unless the work throws the given exception; the test carries on afterwards, so it can
     * check that the refusal left everything as it was.
     *
     * @param class-string<\Throwable> $exception
     * @param callable(): mixed $work
     */
    protected function assertRefusedWith(string $exception, callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exception, $e);

            return;
        }

        $this->fail("Expected $exception, but nothing was thrown.");
    }

    protected function countRows(string $table): int
    {
        $count = $this->db->query("SELECT COUNT(*) AS n FROM `$table`")[0]['n'] ?? 0;
        assert(is_numeric($count));

        return (int) $count;
    }

    /**
     * @param array<string, string|int|null> $row
     */
    private function insert(string $table, array $row): int
    {
        $columns = implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($row)));
        $marks = implode(', ', array_map(static fn (string $c): string => ":$c", array_keys($row)));

        $this->db->execute("INSERT INTO `$table` ($columns) VALUES ($marks)", $row);

        return (int) $this->db->lastInsertId();
    }
}

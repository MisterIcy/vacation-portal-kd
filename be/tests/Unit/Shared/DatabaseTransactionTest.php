<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Database;
use Exception;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * Runs the transaction logic against an in-memory SQLite database, so it is tested
 * without a MySQL server. Locking behaviour itself is MySQL's and is not covered here.
 */
class DatabaseTransactionTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database([], new PDO('sqlite::memory:'));
        $this->db->execute('CREATE TABLE items (name TEXT NOT NULL)');
    }

    public function testCommitsWhenTheCallbackSucceedsAndReturnsItsResult(): void
    {
        $result = $this->db->transactional(function (): string {
            $this->db->execute("INSERT INTO items (name) VALUES ('a')");

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame([['name' => 'a']], $this->db->query('SELECT name FROM items'));
    }

    public function testRollsBackEverythingAndRethrowsWhenTheCallbackFails(): void
    {
        $caught = $this->thrownBy(function (): void {
            $this->db->transactional(function (): void {
                $this->db->execute("INSERT INTO items (name) VALUES ('a')");
                throw new RuntimeException('boom');
            });
        });

        $this->assertSame('boom', $caught?->getMessage(), 'the exception propagates');
        $this->assertSame([], $this->db->query('SELECT name FROM items'), 'the insert was rolled back');
    }

    public function testRetriesOnceAfterADeadlockAndStartsFromACleanSlate(): void
    {
        $attempts = 0;

        $result = $this->db->transactional(function () use (&$attempts): string {
            $attempts++;
            $this->db->execute("INSERT INTO items (name) VALUES ('attempt $attempts')");

            if ($attempts === 1) {
                throw $this->driverError(1213);
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $attempts);
        $this->assertSame([['name' => 'attempt 2']], $this->db->query('SELECT name FROM items'), 'the first attempt was undone');
    }

    public function testRetriesAfterALockWaitTimeoutToo(): void
    {
        $attempts = 0;

        $this->db->transactional(function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw $this->driverError(1205);
            }
        });

        $this->assertSame(2, $attempts);
    }

    public function testGivesUpAfterTheSecondDeadlock(): void
    {
        $attempts = 0;

        $caught = $this->thrownBy(function () use (&$attempts): void {
            $this->db->transactional(function () use (&$attempts): void {
                $attempts++;
                throw $this->driverError(1213);
            });
        });

        $this->assertInstanceOf(PDOException::class, $caught, 'the second failure propagates');
        $this->assertSame(1213, $caught->errorInfo[1] ?? null);
        $this->assertSame(2, $attempts, 'one retry, not an endless loop');
    }

    public function testDoesNotRetryOtherErrors(): void
    {
        $attempts = 0;

        $this->expectException(PDOException::class);

        try {
            $this->db->transactional(function () use (&$attempts): void {
                $attempts++;
                throw $this->driverError(1062); // duplicate key: running it again would fail the same way
            });
        } finally {
            $this->assertSame(1, $attempts);
        }
    }

    public function testNestedUseJoinsTheOuterTransactionInsteadOfFailing(): void
    {
        $this->db->transactional(function (): void {
            $this->db->transactional(function (): void {
                $this->db->execute("INSERT INTO items (name) VALUES ('inner')");
            });
        });

        $this->assertSame([['name' => 'inner']], $this->db->query('SELECT name FROM items'));
    }

    /**
     * @param callable(): void $action
     */
    private function thrownBy(callable $action): ?Throwable
    {
        try {
            $action();
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    private function driverError(int $driverCode): PDOException
    {
        $exception = new PDOException("driver error $driverCode");
        (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, 'HY000');
        $exception->errorInfo = ['HY000', $driverCode, "driver error $driverCode"];

        return $exception;
    }
}

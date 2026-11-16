<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\DatabaseErrors;
use Exception;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class DatabaseErrorsTest extends TestCase
{
    public function testDetectsDuplicateEntry(): void
    {
        $exception = $this->pdoException('23000', 1062);

        $this->assertTrue(DatabaseErrors::isDuplicateEntry($exception));
        $this->assertSame(1062, DatabaseErrors::driverErrorCode($exception));
    }

    public function testDoesNotTreatOtherIntegrityViolationsAsDuplicates(): void
    {
        // Same SQLSTATE class (23000) as a duplicate key, different problem.
        $this->assertFalse(DatabaseErrors::isDuplicateEntry($this->pdoException('23000', 1452))); // foreign key
        $this->assertFalse(DatabaseErrors::isDuplicateEntry($this->pdoException('23000', 1048))); // NOT NULL
    }

    public function testOnlyDeadlocksAndLockWaitTimeoutsAreRetryable(): void
    {
        $this->assertTrue(DatabaseErrors::isRetryable($this->pdoException('40001', 1213)), 'deadlock');
        $this->assertTrue(DatabaseErrors::isRetryable($this->pdoException('HY000', 1205)), 'lock wait timeout');
        $this->assertFalse(DatabaseErrors::isRetryable($this->pdoException('23000', 1062)), 'a duplicate key fails again');
        $this->assertFalse(DatabaseErrors::isRetryable(new PDOException('connection lost')));
    }

    public function testIgnoresExceptionsWithoutDriverErrorInfo(): void
    {
        $exception = new PDOException('connection lost');

        $this->assertNull(DatabaseErrors::driverErrorCode($exception));
        $this->assertFalse(DatabaseErrors::isDuplicateEntry($exception));
    }

    /**
     * Shaped like a real PDO exception: the code is the SQLSTATE string and the
     * driver's own error number is in errorInfo[1].
     */
    private function pdoException(string $sqlState, int $driverCode): PDOException
    {
        $exception = new PDOException("SQLSTATE[$sqlState]: error $driverCode");
        (new ReflectionProperty(Exception::class, 'code'))->setValue($exception, $sqlState);
        $exception->errorInfo = [$sqlState, $driverCode, 'message'];

        return $exception;
    }
}

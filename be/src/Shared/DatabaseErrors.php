<?php

declare(strict_types=1);

namespace App\Shared;

use PDOException;

/**
 * Identifies database errors by MySQL's own error number.
 *
 * PDOException::getCode() holds the SQLSTATE as a *string* (e.g. "23000"), and
 * that class is shared by several different failures (duplicate key, foreign key,
 * NOT NULL). The driver's error number, in errorInfo[1], tells them apart.
 */
final class DatabaseErrors
{
    /** Duplicate entry for a unique key. */
    public const int DUPLICATE_ENTRY = 1062;

    /** A CHECK constraint was violated. */
    public const int CHECK_VIOLATION = 3819;

    /** Lock wait timeout exceeded; try restarting the transaction. */
    public const int LOCK_WAIT_TIMEOUT = 1205;

    /** Deadlock found; the transaction was rolled back and can be retried. */
    public const int DEADLOCK = 1213;

    public static function isDuplicateEntry(PDOException $exception): bool
    {
        return self::driverErrorCode($exception) === self::DUPLICATE_ENTRY;
    }

    /**
     * A value was refused by a rule declared in the database (CHECK constraint). The
     * application validates first, so this means something got past it.
     */
    public static function isCheckViolation(PDOException $exception): bool
    {
        return self::driverErrorCode($exception) === self::CHECK_VIOLATION;
    }

    /**
     * Whether running the whole transaction again can succeed: another transaction was
     * in the way, and nothing about this one was wrong.
     */
    public static function isRetryable(PDOException $exception): bool
    {
        return in_array(self::driverErrorCode($exception), [self::DEADLOCK, self::LOCK_WAIT_TIMEOUT], true);
    }

    public static function driverErrorCode(PDOException $exception): ?int
    {
        $code = $exception->errorInfo[1] ?? null;

        return is_int($code) ? $code : null;
    }
}

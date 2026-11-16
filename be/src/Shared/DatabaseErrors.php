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

    public static function isDuplicateEntry(PDOException $exception): bool
    {
        return self::driverErrorCode($exception) === self::DUPLICATE_ENTRY;
    }

    public static function driverErrorCode(PDOException $exception): ?int
    {
        $code = $exception->errorInfo[1] ?? null;

        return is_int($code) ? $code : null;
    }
}

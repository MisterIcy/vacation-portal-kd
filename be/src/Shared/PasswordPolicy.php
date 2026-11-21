<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;

/**
 * The longest password the application will hash.
 *
 * Argon2id is slow and memory hungry on purpose. Without an upper bound a single request with a
 * multi-megabyte "password" makes the server do (and hold) far more work than a login needs, and the
 * login endpoint is public. A limit this generous is no restriction on a person (a password manager's
 * output is a tiny fraction of it) but removes that lever. It is counted in bytes, which is what is hashed.
 *
 * Creating or changing a password and logging in ask the same question here, so they cannot disagree:
 * a password that can be set can always be used to log in.
 */
final class PasswordPolicy
{
    public const int MAX_BYTES = 1024;

    /**
     * @throws InvalidArgumentException when the password is longer than the limit
     */
    public static function assertNotTooLong(string $password): void
    {
        if (strlen($password) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Password must be at most ' . self::MAX_BYTES . ' bytes.');
        }
    }
}

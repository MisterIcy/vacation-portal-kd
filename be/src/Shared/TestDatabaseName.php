<?php

declare(strict_types=1);

namespace App\Shared;

use InvalidArgumentException;

/**
 * The one rule for which database the test tooling may use: its name must say it is a test database.
 *
 * The integration tests roll back what they write, but `composer db:verify-migrations` rolls the whole
 * schema back, so a TEST_DB_DATABASE that points at real data would be wiped. Both the migration
 * configuration (phinx.php) and the test base class ask here, so the two cannot disagree.
 */
final class TestDatabaseName
{
    public const string DEFAULT = 'vacation_test';

    /**
     * "test" has to be a whole word (vacation_test, test_vacation, vacation_test_2): a name that merely
     * contains the letters, like "latest" or "testimonials", does not count.
     */
    private const string PATTERN = '/(^|[^a-z0-9])test([^a-z0-9]|$)/i';

    /**
     * @throws InvalidArgumentException when the name does not mark the database as a test database
     */
    public static function assertSafe(string $name): string
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(
                "Refusing to use '$name' as the test database: its name must contain the word 'test' (for example " . self::DEFAULT . ').',
            );
        }

        return $name;
    }
}

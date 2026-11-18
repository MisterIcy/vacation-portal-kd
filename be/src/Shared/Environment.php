<?php

declare(strict_types=1);

namespace App\Shared;

use Dotenv\Dotenv;

/**
 * Makes the settings available in $_ENV from whichever place provides them.
 *
 * A .env file is optional. In a deployment the platform sets real environment variables, but
 * PHP's recommended production settings leave $_ENV empty for those, so they are read from
 * the process environment as well. A real variable wins over the file.
 */
final class Environment
{
    public static function load(string $directory): void
    {
        // Real variables first: Dotenv never overwrites a value that is already set.
        foreach (getenv() as $name => $value) {
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
        }

        Dotenv::createImmutable($directory)->safeLoad();
    }
}

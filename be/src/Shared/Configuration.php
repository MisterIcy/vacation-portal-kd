<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Checks the environment once, at start-up, so a misconfigured deployment fails immediately with
 * a clear message instead of failing on the first request that happens to need a missing value.
 *
 * Messages name the setting and never print its value.
 */
final class Configuration
{
    /** Settings the application cannot run without. */
    public const array REQUIRED = ['JWT_SECRET', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'];

    /** HS256 needs a secret at least as long as its 256-bit output. */
    private const int MIN_SECRET_BYTES = 32;

    /** The value shipped in .env.example; anyone can read it in the repository. */
    private const string PLACEHOLDER_SECRET = 'your_jwt_secret_key_here';

    /**
     * Stops the application when the configuration cannot work, or, in production, when the
     * signing secret is one that should not be trusted.
     *
     * @param array<array-key, mixed> $environment
     * @throws ConfigurationError
     */
    public static function assertValid(array $environment): void
    {
        $problems = self::problems($environment);

        if (self::isProduction($environment)) {
            $problems = [...$problems, ...self::warnings($environment)];
        }

        if ($problems !== []) {
            throw new ConfigurationError('Invalid configuration: ' . implode(' ', $problems));
        }
    }

    /**
     * Things that make the application unable to run.
     *
     * @param array<array-key, mixed> $environment
     * @return list<string>
     */
    public static function problems(array $environment): array
    {
        $problems = [];

        foreach (self::REQUIRED as $key) {
            $value = $environment[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $problems[] = "$key is not set.";
            }
        }

        return $problems;
    }

    /**
     * Things that work but should not be relied on. Tolerated while developing, refused in production.
     *
     * @param array<array-key, mixed> $environment
     * @return list<string>
     */
    public static function warnings(array $environment): array
    {
        $secret = $environment['JWT_SECRET'] ?? null;
        if (!is_string($secret) || $secret === '') {
            return []; // already reported as a problem
        }

        $warnings = [];

        if ($secret === self::PLACEHOLDER_SECRET) {
            $warnings[] = 'JWT_SECRET is the placeholder from .env.example; anyone can forge a login token with it.';
        } elseif (strlen($secret) < self::MIN_SECRET_BYTES) {
            $warnings[] = 'JWT_SECRET is shorter than ' . self::MIN_SECRET_BYTES . ' bytes and can be guessed.';
        }

        return $warnings;
    }

    /**
     * @param array<array-key, mixed> $environment
     */
    private static function isProduction(array $environment): bool
    {
        return ($environment['APP_ENV'] ?? null) === 'production';
    }
}

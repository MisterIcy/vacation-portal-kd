<?php

declare(strict_types=1);

namespace App\Shared;

use RuntimeException;

/**
 * The application cannot start with the configuration it was given.
 */
final class ConfigurationError extends RuntimeException
{
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseTest extends TestCase
{
    public function testDsnIncludesPortWhenConfigured(): void
    {
        $dsn = Database::buildDsn([
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => '3307',
            'database' => 'vacation',
            'charset' => 'utf8mb4',
        ]);

        $this->assertSame('mysql:host=db.internal;port=3307;dbname=vacation;charset=utf8mb4', $dsn);
    }

    public function testDsnOmitsPortWhenNotConfigured(): void
    {
        $dsn = Database::buildDsn([
            'driver' => 'mysql',
            'host' => 'db.internal',
            'database' => 'vacation',
            'charset' => 'utf8mb4',
        ]);

        $this->assertSame('mysql:host=db.internal;dbname=vacation;charset=utf8mb4', $dsn);
    }

    public function testSessionIsPinnedToUtcAndStrictMode(): void
    {
        $this->assertStringContainsString("time_zone = '+00:00'", Database::SESSION_INIT_SQL);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', Database::SESSION_INIT_SQL);
    }

    public function testConnectionFailureDoesNotExposeDriverDetails(): void
    {
        try {
            // Port 1 on loopback: nothing listens, so the driver fails to connect.
            new Database([
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '1',
                'database' => 'vacation',
                'charset' => 'utf8mb4',
                'username' => 'secret-user',
                'password' => 'secret-password',
            ]);
            $this->fail('Expected the connection to fail');
        } catch (RuntimeException $e) {
            $this->assertSame('Database connection failed', $e->getMessage());
            $this->assertStringNotContainsString('secret-user', $e->getMessage());
            $this->assertStringNotContainsString('secret-password', $e->getMessage());
            $this->assertNotNull($e->getPrevious(), 'driver error is kept for diagnostics');
        }
    }
}

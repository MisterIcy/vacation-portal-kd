<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/envtest_' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['ENV_TEST_FROM_FILE', 'ENV_TEST_FROM_PROCESS', 'ENV_TEST_BOTH'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        @unlink($this->directory . '/.env');
        @rmdir($this->directory);
    }

    public function testWorksWithoutAnEnvFile(): void
    {
        putenv('ENV_TEST_FROM_PROCESS=set-by-the-platform');
        unset($_ENV['ENV_TEST_FROM_PROCESS']);

        Environment::load($this->directory);

        $this->assertSame('set-by-the-platform', $this->loaded('ENV_TEST_FROM_PROCESS'));
    }

    public function testReadsTheEnvFileWhenThereIsOne(): void
    {
        file_put_contents($this->directory . '/.env', "ENV_TEST_FROM_FILE=from-the-file\n");

        Environment::load($this->directory);

        $this->assertSame('from-the-file', $this->loaded('ENV_TEST_FROM_FILE'));
    }

    public function testThePlatformsVariableWinsOverTheFile(): void
    {
        file_put_contents($this->directory . '/.env', "ENV_TEST_BOTH=from-the-file\n");
        putenv('ENV_TEST_BOTH=from-the-platform');
        unset($_ENV['ENV_TEST_BOTH']);

        Environment::load($this->directory);

        $this->assertSame('from-the-platform', $this->loaded('ENV_TEST_BOTH'));
    }

    /**
     * What ended up in $_ENV, read through a typed copy because the tests above change it at run time.
     */
    private function loaded(string $name): mixed
    {
        /** @var array<string, mixed> $environment */
        $environment = $_ENV;

        return $environment[$name] ?? null;
    }
}

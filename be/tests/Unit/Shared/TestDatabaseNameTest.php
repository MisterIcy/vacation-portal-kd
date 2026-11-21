<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\TestDatabaseName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TestDatabaseNameTest extends TestCase
{
    #[DataProvider('acceptedNames')]
    public function testANameThatSaysTestIsAccepted(string $name): void
    {
        $this->assertSame($name, TestDatabaseName::assertSafe($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedNames(): array
    {
        return [
            'the default' => [TestDatabaseName::DEFAULT],
            'a prefix' => ['test_vacation'],
            'a suffix' => ['vacation_test'],
            'in the middle' => ['vacation_test_2'],
            'a hyphen' => ['vacation-test'],
            'capitals' => ['Vacation_TEST'],
            'only the word' => ['test'],
        ];
    }

    #[DataProvider('refusedNames')]
    public function testAnyOtherNameIsRefusedAndTheMessageNamesIt(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Refusing to use '$name' as the test database");

        TestDatabaseName::assertSafe($name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedNames(): array
    {
        return [
            'the development database' => ['vacation'],
            'the compose default' => ['vacation_db'],
            'production' => ['vacation_prod'],
            'letters that only contain test' => ['latest'],
            'another such word' => ['testimonials'],
            'glued on' => ['vacationtest'],
            'empty' => [''],
        ];
    }

    /**
     * What protects the destructive command is that the migration configuration really asks. Loading it
     * in a separate process shows that, with the environment a shell would have.
     */
    public function testThePhinxConfigurationRefusesADatabaseThatIsNotATestDatabase(): void
    {
        [$exit, $output] = $this->loadPhinxConfiguration(['TEST_DB_DATABASE' => 'vacation']);

        $this->assertNotSame(0, $exit);
        $this->assertStringContainsString("Refusing to use 'vacation' as the test database", $output);
    }

    public function testThePhinxConfigurationUsesTheTestDatabaseByDefaultAndWhenNamedLikeOne(): void
    {
        [$exit, $output] = $this->loadPhinxConfiguration([]);
        $this->assertSame(0, $exit, $output);
        $this->assertSame(TestDatabaseName::DEFAULT, trim($output));

        [$exit, $output] = $this->loadPhinxConfiguration(['TEST_DB_DATABASE' => 'ci_test_7']);
        $this->assertSame(0, $exit, $output);
        $this->assertSame('ci_test_7', trim($output));
    }

    /**
     * @param array<string, string> $environment
     * @return array{int, string}
     */
    private function loadPhinxConfiguration(array $environment): array
    {
        $phinx = dirname(__DIR__, 3) . '/phinx.php';
        $code = '$config = require ' . var_export($phinx, true) . '; echo $config["environments"]["testing"]["name"];';

        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment + [
                'DB_HOST' => 'unused', 'DB_PORT' => '3306', 'DB_DATABASE' => 'unused',
                'DB_USERNAME' => 'unused', 'DB_PASSWORD' => 'unused', 'DB_CHARSET' => 'utf8mb4',
                'PATH' => (string) getenv('PATH'),
            ],
        );
        $this->assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}

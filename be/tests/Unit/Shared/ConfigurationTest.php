<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Configuration;
use App\Shared\ConfigurationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private const string STRONG_SECRET = 'k3J8vQ2mZx9RtLw5NpY7aBc4DfGh6SuE1oIiUqWe';

    public function testAValidConfigurationHasNoProblems(): void
    {
        $this->assertSame([], Configuration::problems($this->environment()));
        Configuration::assertValid($this->environment());
        $this->addToAssertionCount(1);
    }

    #[DataProvider('requiredSettings')]
    public function testEveryRequiredSettingMustBePresent(string $key): void
    {
        $missing = $this->environment();
        unset($missing[$key]);

        $this->assertReportedAndRefused($missing, $key);
    }

    #[DataProvider('requiredSettings')]
    public function testEveryRequiredSettingMustNotBeEmpty(string $key): void
    {
        $this->assertReportedAndRefused([...$this->environment(), $key => '   '], $key);
    }

    /**
     * @param array<string, string> $environment
     */
    private function assertReportedAndRefused(array $environment, string $key): void
    {
        $reported = array_filter(Configuration::problems($environment), fn (string $problem) => str_contains($problem, $key));
        $this->assertCount(1, $reported, "$key is reported");

        $refused = false;
        try {
            Configuration::assertValid($environment);
        } catch (ConfigurationError) {
            $refused = true;
        }
        $this->assertTrue($refused, "start-up is refused without $key");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredSettings(): array
    {
        return array_combine(
            Configuration::REQUIRED,
            array_map(fn (string $key) => [$key], Configuration::REQUIRED)
        );
    }

    public function testAShortOrPlaceholderSecretIsOnlyAWarningInDevelopment(): void
    {
        foreach (['short', 'your_jwt_secret_key_here'] as $weak) {
            $environment = [...$this->environment(), 'JWT_SECRET' => $weak];

            Configuration::assertValid($environment); // does not throw
            $warnings = Configuration::warnings($environment);

            $this->assertCount(1, $warnings);
            $this->assertStringContainsString('JWT_SECRET', $warnings[0]);
        }
    }

    public function testAShortOrPlaceholderSecretStopsTheApplicationInProduction(): void
    {
        $environment = [...$this->environment(), 'APP_ENV' => 'production', 'JWT_SECRET' => 'your_jwt_secret_key_here'];

        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('JWT_SECRET');

        Configuration::assertValid($environment);
    }

    public function testAStrongSecretIsAcceptedInProduction(): void
    {
        Configuration::assertValid([...$this->environment(), 'APP_ENV' => 'production']);

        $this->assertSame([], Configuration::warnings($this->environment()));
    }

    public function testTheSecretLengthIsMeasuredInBytes(): void
    {
        $this->assertNotSame([], Configuration::warnings([...$this->environment(), 'JWT_SECRET' => str_repeat('a', 31)]));
        $this->assertSame([], Configuration::warnings([...$this->environment(), 'JWT_SECRET' => str_repeat('a', 32)]));
    }

    public function testMessagesNameTheSettingButNeverTheSecretValue(): void
    {
        $environment = [...$this->environment(), 'JWT_SECRET' => 'weak-value-abc', 'DB_PASSWORD' => ''];

        $messages = implode(' ', [...Configuration::problems($environment), ...Configuration::warnings($environment)]);

        $this->assertStringContainsString('JWT_SECRET', $messages);
        $this->assertStringNotContainsString('weak-value-abc', $messages);
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return [
            'JWT_SECRET' => self::STRONG_SECRET,
            'DB_HOST' => 'db',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'vacation',
            'DB_USERNAME' => 'vacation_app',
            'DB_PASSWORD' => 'a-password',
            'DB_CHARSET' => 'utf8mb4',
        ];
    }
}

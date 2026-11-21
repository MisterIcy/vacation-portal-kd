<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\PasswordPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function testAPasswordOfExactlyTheLimitIsAccepted(): void
    {
        PasswordPolicy::assertNotTooLong(str_repeat('a', PasswordPolicy::MAX_BYTES));

        $this->addToAssertionCount(1);
    }

    public function testAPasswordOneByteOverTheLimitIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at most 1024 bytes.');

        PasswordPolicy::assertNotTooLong(str_repeat('a', PasswordPolicy::MAX_BYTES + 1));
    }

    public function testTheLimitCountsBytesBecauseThatIsWhatIsHashed(): void
    {
        // 512 two-byte characters are exactly 1024 bytes; one more character goes over.
        PasswordPolicy::assertNotTooLong(str_repeat('é', 512));

        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::assertNotTooLong(str_repeat('é', 513));
    }

    public function testAnOrdinaryPasswordIsNowhereNearTheLimit(): void
    {
        PasswordPolicy::assertNotTooLong('correct horse battery staple, with a long passphrase on top of it all 12345!');

        $this->addToAssertionCount(1);
    }
}

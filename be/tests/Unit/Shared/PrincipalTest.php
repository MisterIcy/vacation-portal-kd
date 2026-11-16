<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Principal;
use PHPUnit\Framework\TestCase;

class PrincipalTest extends TestCase
{
    public function testAManager(): void
    {
        $principal = Principal::manager(3);

        $this->assertSame(3, $principal->id());
        $this->assertTrue($principal->isManager());
        $this->assertFalse($principal->isEmployee());
    }

    public function testAnEmployee(): void
    {
        $principal = Principal::employee(4);

        $this->assertSame(4, $principal->id());
        $this->assertTrue($principal->isEmployee());
        $this->assertFalse($principal->isManager());
    }
}

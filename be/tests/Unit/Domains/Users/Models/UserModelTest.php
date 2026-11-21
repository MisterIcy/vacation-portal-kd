<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Users\Models;

use App\Domains\Users\Models\UserModel;
use App\Domains\Users\Models\UserRole;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function testValidUserModelCreation(): void
    {
        $now = new DateTime();
        $user = new UserModel(
            1,
            'test@example.com',
            'password123',
            'John Doe ',
            '1234567',
            UserRole::Employee,
            $now,
            $now
        );
        $this->assertSame(1, $user->getId());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('password123', $user->getPassword());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('1234567', $user->getEmployeeCode());
        $this->assertSame(UserRole::Employee, $user->getRole());
        $this->assertSame($now, $user->getCreatedAt());
        $this->assertSame($now, $user->getUpdatedAt());
    }

    public function testNullIdIsAccepted(): void
    {
        $user = new UserModel(
            null,
            'test@example.com',
            'password123',
            'John Doe',
            '1234567',
            UserRole::Manager
        );
        $this->assertNull($user->getId());
        $this->assertSame(UserRole::Manager, $user->getRole());
        $this->assertNull($user->getCreatedAt());
        $this->assertNull($user->getUpdatedAt());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserModel(1, 'invalid-email', 'password123', 'John Doe', '1234567', UserRole::Employee);
    }

    public function testShortPasswordThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserModel(1, 'test@example.com', 'short', 'John Doe', '1234567', UserRole::Employee);
    }

    public function testAPasswordOfExactlyTheMaximumLengthIsAccepted(): void
    {
        $user = new UserModel(1, 'test@example.com', str_repeat('a', 1024), 'John Doe', '1234567', UserRole::Employee);

        $this->assertSame(1024, strlen((string) $user->getPassword()));
    }

    public function testAPasswordLongerThanTheMaximumIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at most 1024 bytes.');

        new UserModel(1, 'test@example.com', str_repeat('a', 1025), 'John Doe', '1234567', UserRole::Employee);
    }

    public function testEmptyNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserModel(1, 'test@example.com', 'password123', '   ', '1234567', UserRole::Employee);
    }

    public function testInvalidEmployeeCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserModel(1, 'test@example.com', 'password123', 'John Doe', '12345', UserRole::Employee);
    }

    public function testTimestampsAreSerializedAsIsoStringsWithOffset(): void
    {
        $createdAt = new DateTime('2026-01-15 10:00:00', new DateTimeZone('UTC'));
        $updatedAt = new DateTime('2026-01-16 11:30:00', new DateTimeZone('UTC'));
        $user = new UserModel(1, 'a@example.com', null, 'Ann', '1234567', UserRole::Employee, $createdAt, $updatedAt);

        $data = $user->toArray();

        $this->assertSame('2026-01-15T10:00:00+00:00', $data['created_at']);
        $this->assertSame('2026-01-16T11:30:00+00:00', $data['updated_at']);
    }

    public function testMissingTimestampsAreNull(): void
    {
        $user = new UserModel(null, 'a@example.com', null, 'Ann', '1234567', UserRole::Employee);

        $this->assertNull($user->toArray()['created_at']);
        $this->assertNull($user->toArray()['updated_at']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Vacations\Models;

use App\Domains\Vacations\Models\StatusFilter;
use App\Domains\Vacations\Models\VacationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusFilterTest extends TestCase
{
    public function testEachStatusFilterMatchesItsStatus(): void
    {
        $this->assertSame(VacationStatus::Pending, StatusFilter::Pending->status());
        $this->assertSame(VacationStatus::Approved, StatusFilter::Approved->status());
        $this->assertSame(VacationStatus::Rejected, StatusFilter::Rejected->status());
    }

    public function testAllMatchesEverythingSoThereIsNoStatus(): void
    {
        $this->assertNull(StatusFilter::All->status());
    }

    public function testEveryRequestStatusHasAFilterOfTheSameName(): void
    {
        foreach (VacationStatus::cases() as $status) {
            $this->assertSame($status, StatusFilter::from($status->value)->status());
        }
    }

    public function testListsTheAllowedValuesForErrorMessages(): void
    {
        $this->assertSame('pending, approved, rejected, all', StatusFilter::allowedValues());
    }

    #[DataProvider('unknownValues')]
    public function testRejectsAnUnknownValue(string $value): void
    {
        $this->assertNull(StatusFilter::tryFrom($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownValues(): array
    {
        return ['wrong case' => ['PENDING'], 'empty' => [''], 'another word' => ['everything'], 'padded' => [' pending']];
    }
}

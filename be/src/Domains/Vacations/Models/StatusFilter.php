<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Models;

/**
 * Which vacation requests a list should contain, as the API's `status` query parameter puts it.
 * A request status is one of three; "all" is the fourth choice, for no filtering.
 */
enum StatusFilter: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case All = 'all';

    /**
     * The status to match, or null when every request qualifies.
     */
    public function status(): ?VacationStatus
    {
        return match ($this) {
            self::Pending => VacationStatus::Pending,
            self::Approved => VacationStatus::Approved,
            self::Rejected => VacationStatus::Rejected,
            self::All => null,
        };
    }

    /**
     * The values a client may send, for error messages.
     */
    public static function allowedValues(): string
    {
        return implode(', ', array_map(fn (self $case) => $case->value, self::cases()));
    }
}

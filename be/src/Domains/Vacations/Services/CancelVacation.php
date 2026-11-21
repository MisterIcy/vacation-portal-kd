<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\AccessDenied;
use App\Shared\Principal;
use InvalidArgumentException;

/**
 * Use case: an employee withdraws one of their own requests while it is still pending.
 */
readonly class CancelVacation
{
    public function __construct(private VacationRequestRepository $vacations)
    {
    }

    /**
     * @throws AccessDenied when the caller is not an employee
     * @throws InvalidArgumentException when there is no such request, or it is someone else's
     * @throws VacationNotPending when it was already decided
     */
    public function __invoke(Principal $by, int $vacationId): void
    {
        if (!$by->isEmployee()) {
            throw AccessDenied::forRole();
        }

        // Who may cancel, and when, is decided by the vacation request itself.
        $this->vacations->findById($vacationId)->cancel($by->id());

        $this->vacations->delete($vacationId);
    }
}

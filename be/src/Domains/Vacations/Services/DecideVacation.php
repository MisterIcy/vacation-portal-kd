<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\AccessDenied;
use App\Shared\Principal;
use Closure;
use InvalidArgumentException;

/**
 * Use case: a manager approves or rejects a pending vacation request.
 *
 * The request itself enforces that it is still pending; the repository's conditional write
 * is what keeps two managers deciding at the same moment from both succeeding.
 */
readonly class DecideVacation
{
    public function __construct(private VacationRequestRepository $vacations)
    {
    }

    /**
     * @throws AccessDenied when the caller is not a manager, or is the one who asked for this leave
     * @throws InvalidArgumentException when there is no such request
     * @throws VacationNotPending when it was already decided
     */
    public function approve(Principal $by, int $vacationId): VacationRequest
    {
        return $this->decide($by, $vacationId, fn (VacationRequest $vacation) => $vacation->approve($by->id()));
    }

    /**
     * @throws AccessDenied when the caller is not a manager, or is the one who asked for this leave
     * @throws InvalidArgumentException when there is no such request
     * @throws VacationNotPending when it was already decided
     */
    public function reject(Principal $by, int $vacationId): VacationRequest
    {
        return $this->decide($by, $vacationId, fn (VacationRequest $vacation) => $vacation->reject($by->id()));
    }

    /**
     * @param Closure(VacationRequest): VacationRequest $decision
     */
    private function decide(Principal $by, int $vacationId, Closure $decision): VacationRequest
    {
        if (!$by->isManager()) {
            throw AccessDenied::forRole();
        }

        $vacation = $this->vacations->findById($vacationId);

        // A person can be promoted while they still have a request waiting. The rule is about who asked,
        // not about what role they have today.
        if ($vacation->getUserId() === $by->id()) {
            throw AccessDenied::forOwnRequest();
        }

        $decided = $decision($vacation);

        return $this->vacations->updateStatus($vacationId, $by->id(), $decided->getStatus());
    }
}

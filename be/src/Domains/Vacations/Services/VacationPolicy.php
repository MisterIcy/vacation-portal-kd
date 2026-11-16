<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Exceptions\VacationOverlap;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\Clock;
use InvalidArgumentException;

/**
 * Rules for submitting a vacation request that look beyond the request itself: they
 * depend on today's date and on the employee's other requests.
 *
 * Callers must run this inside the transaction that also creates the request, holding the
 * employee's lock, otherwise two simultaneous submissions could each pass the overlap check.
 */
readonly class VacationPolicy
{
    public function __construct(private VacationRequestRepository $vacations, private Clock $clock)
    {
    }

    /**
     * @throws InvalidArgumentException when the vacation would start in the past
     * @throws VacationOverlap when it overlaps a pending or approved request of the same employee
     */
    public function assertCanBeRequested(VacationRequest $request): void
    {
        $period = $request->getPeriod();

        if ($period->startsBefore($this->clock->today())) {
            throw new InvalidArgumentException('A vacation cannot start in the past.');
        }

        // Decided-as-rejected requests do not count: those days are free again.
        if ($this->vacations->hasOverlap($request->getUserId(), $period)) {
            throw VacationOverlap::withExistingRequest();
        }
    }
}

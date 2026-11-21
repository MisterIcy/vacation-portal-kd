<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\AccessDenied;
use App\Shared\Database;
use App\Shared\Principal;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Use case: an employee submits a vacation request.
 *
 * Owns the transaction. Locking the employee first means that two requests from the same
 * person are handled one after the other, so the overlap check always sees the other one.
 */
readonly class RequestVacation
{
    public function __construct(
        private Database $database,
        private VacationRequestRepository $vacations,
        private VacationPolicy $policy,
    ) {
    }

    /**
     * @throws AccessDenied when the caller is not an employee
     * @throws InvalidArgumentException when the request is invalid or breaks a rule
     */
    public function __invoke(Principal $by, DateTimeInterface $from, DateTimeInterface $to, string $reason): VacationRequest
    {
        if (!$by->isEmployee()) {
            throw AccessDenied::forRole();
        }

        // Validates the request itself (dates, reason) before the database is involved.
        $request = new VacationRequest(null, $by->id(), null, $from, $to, $reason);

        return $this->database->transactional(function () use ($request): VacationRequest {
            $this->vacations->lockEmployee($request->getUserId());
            $this->policy->assertCanBeRequested($request);

            return $this->vacations->create($request);
        });
    }
}

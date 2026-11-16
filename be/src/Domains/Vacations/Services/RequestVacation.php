<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Repositories\VacationsRepository;
use App\Shared\Database;
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
        private VacationsRepository $vacations,
        private VacationPolicy $policy,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the request is invalid or breaks a rule
     */
    public function __invoke(int $employeeId, DateTimeInterface $from, DateTimeInterface $to, string $reason): VacationRequest
    {
        // Validates the request itself (dates, reason) before the database is involved.
        $request = new VacationRequest(null, $employeeId, null, $from, $to, $reason);

        return $this->database->transactional(function () use ($request): VacationRequest {
            $this->vacations->lockEmployee($request->getUserId());
            $this->policy->assertCanBeRequested($request);

            return $this->vacations->create($request);
        });
    }
}

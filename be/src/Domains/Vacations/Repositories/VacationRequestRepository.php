<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Repositories;

use App\Domains\Vacations\Models\DateRange;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use App\Shared\ModelCollection;
use InvalidArgumentException;

/**
 * What the vacation use cases need from storage. The domain and application layers depend
 * on this, not on how it is stored, so the rules can be tested without a database.
 */
interface VacationRequestRepository
{
    /**
     * The requests waiting for a manager's decision, with who asked.
     */
    public function findPendingRequests(): ModelCollection;

    /**
     * One employee's own requests, most recent first.
     */
    public function findUserRequests(int $userId): ModelCollection;

    /**
     * @throws InvalidArgumentException when there is no such request
     */
    public function findById(int $id): VacationRequest;

    public function create(VacationRequest $vacationModel): VacationRequest;

    /**
     * Stores a decision, but only if the request is still pending when it is written. That is
     * what stops two managers deciding the same request at the same moment.
     *
     * @throws InvalidArgumentException when the request is gone or was already decided
     */
    public function updateStatus(int $id, int $managerId, VacationStatus $status): VacationRequest;

    public function delete(int $id): void;

    /**
     * Holds a lock on the employee until the surrounding transaction ends, so one employee's
     * submissions are handled one at a time.
     *
     * @throws InvalidArgumentException when the employee does not exist (any more)
     */
    public function lockEmployee(int $employeeId): void;

    /**
     * Whether the employee has a pending or approved request sharing a day with the range.
     */
    public function hasOverlap(int $employeeId, DateRange $range): bool;
}

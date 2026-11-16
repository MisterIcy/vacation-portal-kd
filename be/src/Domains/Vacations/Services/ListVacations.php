<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\ModelCollection;
use App\Shared\Principal;

/**
 * Use case: show the vacation requests the caller is meant to see. A manager sees the
 * requests waiting for a decision; an employee sees their own.
 */
readonly class ListVacations
{
    public function __construct(private VacationRequestRepository $vacations)
    {
    }

    public function __invoke(Principal $by): ModelCollection
    {
        return $by->isManager()
            ? $this->vacations->findPendingRequests()
            : $this->vacations->findUserRequests($by->id());
    }
}

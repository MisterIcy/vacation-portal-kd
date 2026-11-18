<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Services;

use App\Domains\Vacations\Models\StatusFilter;
use App\Domains\Vacations\Repositories\VacationRequestRepository;
use App\Shared\ModelCollection;
use App\Shared\Principal;

/**
 * Use case: show the vacation requests the caller is meant to see.
 *
 * A manager sees every employee's requests, by default the ones waiting for a decision, and can
 * ask for decided ones or all of them. An employee only ever sees their own, by default all of
 * them; a filter narrows that list but can never widen it to anyone else's.
 */
readonly class ListVacations
{
    public function __construct(private VacationRequestRepository $vacations)
    {
    }

    public function __invoke(Principal $by, ?StatusFilter $filter = null): ModelCollection
    {
        if ($by->isManager()) {
            return $this->vacations->findRequests($filter ?? StatusFilter::Pending);
        }

        return $this->vacations->findUserRequests($by->id(), $filter ?? StatusFilter::All);
    }
}

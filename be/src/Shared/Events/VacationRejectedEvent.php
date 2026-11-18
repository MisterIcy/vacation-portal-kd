<?php

declare(strict_types=1);

namespace App\Shared\Events;

use App\Shared\Notifications\VacationDecisionNotification;

/**
 * A manager rejected a vacation request. Dispatched after the decision is stored.
 */
final readonly class VacationRejectedEvent
{
    public function __construct(public VacationDecisionNotification $notification)
    {
    }
}

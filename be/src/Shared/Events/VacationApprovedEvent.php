<?php

declare(strict_types=1);

namespace App\Shared\Events;

use App\Shared\Notifications\VacationDecisionNotification;

/**
 * A manager approved a vacation request. Dispatched after the decision is stored.
 */
final readonly class VacationApprovedEvent
{
    public function __construct(public VacationDecisionNotification $notification)
    {
    }
}

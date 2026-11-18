<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

enum VacationDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}

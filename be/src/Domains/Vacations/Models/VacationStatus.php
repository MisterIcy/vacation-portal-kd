<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Models;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Where a vacation request is in its life: new requests are pending until a manager decides.',
    type: 'string',
    enum: ['pending', 'approved', 'rejected'],
    example: 'pending',
)]
enum VacationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

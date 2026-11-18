<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Models;

use OpenApi\Attributes as OA;

/**
 * What the vacation context needs to know about the employee who made a request: enough
 * to show a manager who is asking. It deliberately is not the Users domain's own model,
 * so leave management does not depend on how user accounts are represented.
 */
#[OA\Schema(
    schema: 'EmployeeSummary',
    description: 'The employee who made a vacation request.',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
        new OA\Property(property: 'employee_code', type: 'string', example: '1234567'),
        new OA\Property(property: 'role', type: 'string', example: 'employee'),
    ],
)]
final readonly class EmployeeSummary
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $employeeCode,
        private string $role,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array{id: int, name: string, email: string, employee_code: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_code' => $this->employeeCode,
            'role' => $this->role,
        ];
    }
}

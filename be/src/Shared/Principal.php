<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Who is performing an operation, as far as the business rules care: an id and whether they
 * are a manager or an employee. It is what the application layer receives instead of
 * an HTTP request or the authentication domain's own user type, so a use case can be called
 * (and tested) without either.
 */
final readonly class Principal
{
    private function __construct(private int $id, private bool $manager)
    {
    }

    public static function manager(int $id): self
    {
        return new self($id, true);
    }

    public static function employee(int $id): self
    {
        return new self($id, false);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function isManager(): bool
    {
        return $this->manager;
    }

    public function isEmployee(): bool
    {
        return !$this->manager;
    }
}

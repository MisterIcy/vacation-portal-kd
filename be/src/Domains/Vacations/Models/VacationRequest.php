<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Models;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Shared\DateTimes;
use DateMalformedStringException;
use DateTime;
use InvalidArgumentException;
use JsonSerializable;

readonly class VacationRequest implements JsonSerializable
{
    private ?int $id;
    private int $userId;
    private ?EmployeeSummary $employee;
    private DateRange $period;
    private string $reason;
    private VacationStatus $status;
    private ?int $authorizedBy;
    private ?DateTime $createdAt;
    private ?DateTime $updatedAt;

    /**
     * @throws DateMalformedStringException
     */
    public function __construct(
        ?int $id,
        int $userId,
        ?EmployeeSummary $employee,
        DateTime $from,
        DateTime $to,
        string $reason,
        VacationStatus $status = VacationStatus::Pending,
        ?int $authorizedBy = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Reason must not be empty.');
        }

        $this->period = new DateRange($from, $to);

        $this->id = $id;
        $this->userId = $userId;
        $this->employee = $employee;
        $this->reason = $reason;
        $this->status = $status;
        $this->authorizedBy = $authorizedBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmployee(): ?EmployeeSummary
    {
        return $this->employee;
    }

    public function getPeriod(): DateRange
    {
        return $this->period;
    }

    public function getFrom(): DateTime
    {
        return DateTime::createFromImmutable($this->period->from());
    }

    public function getTo(): DateTime
    {
        return DateTime::createFromImmutable($this->period->to());
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getStatus(): VacationStatus
    {
        return $this->status;
    }

    public function getAuthorizedBy(): ?int
    {
        return $this->authorizedBy;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function isPending(): bool
    {
        return $this->status === VacationStatus::Pending;
    }

    /**
     * A manager's decision. Only a pending request can be decided, and only once.
     *
     * @throws VacationNotPending
     */
    public function approve(int $managerId): self
    {
        return $this->decide(VacationStatus::Approved, $managerId);
    }

    /**
     * @throws VacationNotPending
     */
    public function reject(int $managerId): self
    {
        return $this->decide(VacationStatus::Rejected, $managerId);
    }

    /**
     * Withdrawing a request is allowed for its owner while it is still pending.
     * Someone else gets the same answer as for a request that does not exist, so
     * they cannot find out which ids are in use.
     *
     * @throws InvalidArgumentException when the requester is not the owner
     * @throws VacationNotPending
     */
    public function cancel(int $requesterId): void
    {
        if ($requesterId !== $this->userId) {
            throw new InvalidArgumentException("Vacation with ID {$this->id} not found.");
        }

        if (!$this->isPending()) {
            throw VacationNotPending::forDeletion();
        }
    }

    /**
     * @throws VacationNotPending
     */
    private function decide(VacationStatus $decision, int $managerId): self
    {
        if (!$this->isPending()) {
            throw VacationNotPending::forUpdate();
        }

        return new self(
            $this->id,
            $this->userId,
            $this->employee,
            $this->getFrom(),
            $this->getTo(),
            $this->reason,
            $decision,
            $managerId,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    /**
     * Total days of the vacation, counting both the first and the last day.
     */
    public function getDays(): int
    {
        return $this->period->days();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'user' => $this->employee?->toArray(),
            'from' => $this->period->from()->format('Y-m-d'),
            'to' => $this->period->to()->format('Y-m-d'),
            'days' => $this->getDays(),
            'reason' => $this->reason,
            'status' => $this->status->value,
            'authorized_by' => $this->authorizedBy,
            'created_at' => DateTimes::toIso($this->createdAt),
            'updated_at' => DateTimes::toIso($this->updatedAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

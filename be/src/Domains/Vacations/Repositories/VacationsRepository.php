<?php

declare(strict_types=1);

namespace App\Domains\Vacations\Repositories;

use App\Domains\Vacations\Exceptions\VacationNotPending;
use App\Domains\Vacations\Models\DateRange;
use App\Domains\Vacations\Models\EmployeeSummary;
use App\Domains\Vacations\Models\StatusFilter;
use App\Domains\Vacations\Models\VacationRequest;
use App\Domains\Vacations\Models\VacationStatus;
use App\Shared\Database;
use App\Shared\DatabaseErrors;
use App\Shared\DateTimes;
use App\Shared\ModelCollection;
use App\Shared\NotFound;
use DateMalformedStringException;
use InvalidArgumentException;
use PDOException;

readonly class VacationsRepository implements VacationRequestRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function findRequests(StatusFilter $filter): ModelCollection
    {
        $status = $filter->status();

        // Only fixed fragments are joined into the SQL; the status itself is always a bound parameter.
        $where = $status === null ? '' : ' WHERE v.status = :status';
        // The queue of pending requests is worked from the earliest start; history reads newest first.
        $order = $status === VacationStatus::Pending ? 'ASC' : 'DESC';

        $sql = "SELECT v.id, v.user_id, v.`from`, v.`to`, v.reason, v.status, v.authorized_by, v.created_at, v.updated_at,
            u.name AS employee_name, u.email AS employee_email, u.employee_code, u.role AS employee_role
            FROM vacations v INNER JOIN users u ON v.user_id = u.id{$where} ORDER BY v.`from` {$order}";

        $rows = $this->db->query($sql, $status === null ? [] : ['status' => $status->value]);

        $vacations = new ModelCollection();

        foreach ($rows as $data) {
            $vacation = self::mapToVacationRequest($data, self::mapToEmployeeSummary($data));
            $vacations->add($vacation);
        }

        return $vacations;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function findUserRequests(int $userId, StatusFilter $filter = StatusFilter::All): ModelCollection
    {
        $status = $filter->status();
        $and = $status === null ? '' : ' AND status = :status';

        $sql = "SELECT id, user_id, `from`, `to`, reason, status, authorized_by, created_at, updated_at FROM vacations WHERE user_id = :user_id{$and} ORDER BY `from` DESC";

        $params = ['user_id' => $userId];
        if ($status !== null) {
            $params['status'] = $status->value;
        }

        $rows = $this->db->query($sql, $params);

        $vacations = new ModelCollection();

        foreach ($rows as $data) {
            $vacation = self::mapToVacationRequest($data);
            $vacations->add($vacation);
        }

        return $vacations;
    }

    /**
     * Locks the employee's row until the surrounding transaction ends, so that one employee's
     * submissions are handled one at a time. The row is only a lock handle: the users table
     * belongs to the Users domain and is not read or changed here.
     *
     * Must be called inside a transaction.
     *
     * @throws InvalidArgumentException when the employee does not exist (any more)
     */
    public function lockEmployee(int $employeeId): void
    {
        $rows = $this->db->query('SELECT id FROM users WHERE id = :id FOR UPDATE', ['id' => $employeeId]);

        if ($rows === []) {
            throw new NotFound("Employee with ID $employeeId not found.");
        }
    }

    /**
     * Whether the employee already has a pending or approved request that shares a day with
     * the range (both ends included). Rejected requests do not block anything.
     */
    public function hasOverlap(int $employeeId, DateRange $range): bool
    {
        $sql = 'SELECT 1 FROM vacations
                WHERE user_id = :user_id AND status IN (:pending, :approved)
                AND `from` <= :to AND `to` >= :from LIMIT 1';

        $rows = $this->db->query($sql, [
            'user_id' => $employeeId,
            'pending' => VacationStatus::Pending->value,
            'approved' => VacationStatus::Approved->value,
            'from' => $range->from()->format('Y-m-d'),
            'to' => $range->to()->format('Y-m-d'),
        ]);

        return $rows !== [];
    }

    /**
     * @throws DateMalformedStringException
     */
    public function findById(int $id): VacationRequest
    {
        // The employee comes with the request: a decision has to tell them, and that needs their name and email.
        $sql = "SELECT v.id, v.user_id, v.`from`, v.`to`, v.reason, v.status, v.authorized_by, v.created_at, v.updated_at,
            u.name AS employee_name, u.email AS employee_email, u.employee_code, u.role AS employee_role
            FROM vacations v INNER JOIN users u ON v.user_id = u.id WHERE v.id = :id";

        $row = $this->db->query($sql, ['id' => $id]);

        if (empty($row)) {
            throw new NotFound("Vacation with ID $id not found.");
        }

        return self::mapToVacationRequest($row[0], self::mapToEmployeeSummary($row[0]));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function updateStatus(int $id, int $managerId, VacationStatus $status): VacationRequest
    {
        $sql = "UPDATE vacations SET status = :status, authorized_by = :authorized_by WHERE id = :id AND status = :old_status";
        $rowCount = $this->db->execute($sql, [
            'status' => $status->value,
            'authorized_by' => $managerId,
            'id' => $id,
            'old_status' => VacationStatus::Pending->value
        ]);

        if ($rowCount === 0) {
            // The caller has just loaded this request and found it pending, so someone else decided
            // it first. This is what keeps two managers from both winning.
            throw VacationNotPending::forUpdate();
        }

        return $this->findById($id);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function create(VacationRequest $vacationModel): VacationRequest
    {
        $sql = "INSERT INTO vacations (user_id, `from`, `to`, reason, status)
                VALUES (:user_id, :from, :to, :reason, :status)";

        try {
            $this->db->execute($sql, [
                'user_id' => $vacationModel->getUserId(),
                'from' => $vacationModel->getFrom()->format('Y-m-d'),
                'to' => $vacationModel->getTo()->format('Y-m-d'),
                'reason' => $vacationModel->getReason(),
                'status' => $vacationModel->getStatus()->value,
            ]);
        } catch (PDOException $e) {
            if (DatabaseErrors::isCheckViolation($e)) {
                throw new InvalidArgumentException('The submitted data violates a database rule.', 0, $e);
            }

            throw $e;
        }

        $id = (int) $this->db->lastInsertId();

        // Fetch created_at and updated_at from DB
        $row = $this->db->query(
            "SELECT created_at, updated_at FROM vacations WHERE id = :id",
            ['id' => $id]
        )[0];

        $createdAt = isset($row['created_at']) && is_string($row['created_at']) ? DateTimes::fromDatabase($row['created_at']) : null;
        $updatedAt = isset($row['updated_at']) && is_string($row['updated_at']) ? DateTimes::fromDatabase($row['updated_at']) : null;

        return new VacationRequest(
            $id,
            $vacationModel->getUserId(),
            $vacationModel->getEmployee(),
            $vacationModel->getFrom(),
            $vacationModel->getTo(),
            $vacationModel->getReason(),
            $vacationModel->getStatus(),
            $vacationModel->getAuthorizedBy(),
            $createdAt,
            $updatedAt
        );
    }

    public function delete(int $id): void
    {
        $sql = "DELETE FROM vacations WHERE id = :id AND status = :status";
        $rowCount = $this->db->execute($sql, ['id' => $id, 'status' => VacationStatus::Pending->value]);

        if ($rowCount === 0) {
            // The caller has just loaded this request, so it exists: it was decided in the meantime.
            throw VacationNotPending::forDeletion();
        }
    }

    /**
     * Reads the employee columns of the manager's query (vacations joined with users).
     * Only this context's own summary is built; nothing is borrowed from the Users domain.
     *
     * @param array<string, mixed> $data
     */
    private static function mapToEmployeeSummary(array $data): EmployeeSummary
    {
        return new EmployeeSummary(
            is_scalar($data['user_id'] ?? null) ? intval($data['user_id']) : 0,
            is_string($data['employee_name'] ?? null) ? $data['employee_name'] : '',
            is_string($data['employee_email'] ?? null) ? $data['employee_email'] : '',
            is_string($data['employee_code'] ?? null) ? $data['employee_code'] : '',
            is_string($data['employee_role'] ?? null) ? $data['employee_role'] : '',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @throws DateMalformedStringException
     */
    public static function mapToVacationRequest(array $data, ?EmployeeSummary $employee = null): VacationRequest
    {
        $id = (isset($data['id']) && is_scalar($data['id'])) ? intval($data['id']) : null;
        $userId = (isset($data['user_id']) && is_scalar($data['user_id'])) ? intval($data['user_id']) : null;
        $from = isset($data['from']) && is_string($data['from']) ? DateTimes::fromDatabase($data['from']) : null;
        $to = isset($data['to']) && is_string($data['to']) ? DateTimes::fromDatabase($data['to']) : null;
        $reason = isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : '';
        $status = isset($data['status']) && is_string($data['status']) ? VacationStatus::from($data['status']) : VacationStatus::Pending;
        $authorizedBy = (isset($data['authorized_by']) && is_scalar($data['authorized_by'])) ? intval($data['authorized_by']) : null;
        $createdAt = isset($data['created_at']) && is_string($data['created_at']) ? DateTimes::fromDatabase($data['created_at']) : null;
        $updatedAt = isset($data['updated_at']) && is_string($data['updated_at']) ? DateTimes::fromDatabase($data['updated_at']) : null;

        assert(!is_null($userId));
        assert(!is_null($from));
        assert(!is_null($to));

        return new VacationRequest(
            $id,
            $userId,
            $employee,
            $from,
            $to,
            $reason,
            $status,
            $authorizedBy,
            $createdAt,
            $updatedAt
        );
    }
}

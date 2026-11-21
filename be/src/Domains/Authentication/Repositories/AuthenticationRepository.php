<?php

declare(strict_types=1);

namespace App\Domains\Authentication\Repositories;

use App\Domains\Authentication\Models\AuthenticatedUserModel;
use App\Domains\Users\Models\UserRole;
use App\Shared\Database;
use InvalidArgumentException;

readonly class AuthenticationRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * The person as they are now (used on every authenticated request), or null if the
     * account no longer exists. No password hash is loaded.
     */
    public function findById(int $id): ?AuthenticatedUserModel
    {
        $rows = $this->db->query('SELECT id, email, name, role FROM users WHERE id = :id', ['id' => $id]);

        if (empty($rows)) {
            return null;
        }

        /** @var array{id: string, name: string, email: string, role: string} $data */
        $data = $rows[0];

        return new AuthenticatedUserModel(
            id: intval($data['id']),
            name: $data['name'],
            email: $data['email'],
            role: UserRole::from($data['role']),
        );
    }

    public function findByEmail(string $email): AuthenticatedUserModel
    {
        $sql = "SELECT id, email, name, password, role FROM users WHERE email = :email";

        $row = $this->db->query($sql, ['email' => $email]);

        if (empty($row)) {
            throw new InvalidArgumentException("User not found.");
        }

        /** @var array{id: string, name: string, password: string, email: string, role: string} $data */
        $data = $row[0];

        return new AuthenticatedUserModel(
            id: intval($data['id']),
            name: $data['name'],
            email: $data['email'],
            role: UserRole::from($data['role']),
            hashedPassword: $data['password'],
        );
    }
}

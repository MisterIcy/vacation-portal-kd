<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Declares two rules in the database as well as in the application, so that data written
 * by anything else (a script, a manual fix, a bug) cannot break them:
 *
 *  - a vacation's first day is not after its last day
 *  - an employee code is exactly 7 digits
 *
 * Phinx's table builder has no CHECK support, hence the SQL. MySQL enforces CHECK constraints
 * from 8.0.16. Adding one validates the existing rows first and fails if any would violate it.
 */
final class AddCheckConstraints extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE `vacations` ADD CONSTRAINT `chk_vacations_date_range` CHECK (`from` <= `to`)');
        $this->execute("ALTER TABLE `users` ADD CONSTRAINT `chk_users_employee_code` CHECK (`employee_code` REGEXP '^[0-9]{7}$')");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE `users` DROP CHECK `chk_users_employee_code`');
        $this->execute('ALTER TABLE `vacations` DROP CHECK `chk_vacations_date_range`');
    }
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Indexes that match how vacation requests are actually read:
 *
 *  - an employee's own requests:   WHERE user_id = ?  ORDER BY `from` DESC   and the overlap check
 *  - the manager's pending queue:  WHERE status = ?   ORDER BY `from`
 *
 * The composite indexes serve these without a separate sort step. They also make two single-column
 * indexes redundant (an index on (a, b) already serves lookups on a), so those are dropped. The
 * foreign key on user_id still has the index it needs: it now starts with user_id.
 *
 * MySQL itself drops the foreign key's implicit user_id index as soon as a usable one exists, so
 * whether it is still there depends on how the table was created; each old index is therefore
 * only dropped if it is present.
 */
final class AddVacationAccessPathIndexes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('vacations')
            ->addIndex(['user_id', 'from'], ['name' => 'idx_vacations_user_from'])
            ->addIndex(['status', 'from'], ['name' => 'idx_vacations_status_from'])
            ->update();

        // Only after the new indexes exist: the foreign key must never be left without one.
        foreach (['user_id', 'status'] as $redundant) {
            $table = $this->table('vacations');
            if ($table->hasIndexByName($redundant)) {
                $table->removeIndexByName($redundant)->update();
            }
        }
    }

    public function down(): void
    {
        foreach (['user_id' => ['user_id'], 'status' => ['status']] as $name => $columns) {
            $table = $this->table('vacations');
            if (!$table->hasIndexByName($name)) {
                $table->addIndex($columns, ['name' => $name])->update();
            }
        }

        $this->table('vacations')
            ->removeIndexByName('idx_vacations_user_from')
            ->removeIndexByName('idx_vacations_status_from')
            ->update();
    }
}

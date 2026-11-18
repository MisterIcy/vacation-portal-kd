<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;

/**
 * What every connection is set up to do, checked against the server rather than assumed.
 */
class DatabaseConnectionTest extends DatabaseTestCase
{
    public function testTheSessionRunsInUtc(): void
    {
        $this->assertSame('+00:00', $this->db->query('SELECT @@session.time_zone AS tz')[0]['tz']);
    }

    public function testTheSessionIsStrict(): void
    {
        $mode = $this->db->query('SELECT @@session.sql_mode AS mode')[0]['mode'];

        $this->assertIsString($mode);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $mode);
        $this->assertStringContainsString('NO_ZERO_DATE', $mode);
    }

    public function testAnOversizedValueIsRefusedInsteadOfBeingCutOff(): void
    {
        $employee = $this->insertUser();

        try {
            $this->insertVacation($employee, '2030-07-01', '2030-07-02', ['reason' => str_repeat('x', 256)]);
            $this->fail('A reason longer than the column should be refused.');
        } catch (PDOException $e) {
            $this->assertSame(1406, $e->errorInfo[1] ?? null);
        }
    }

    public function testAnImpossibleDateIsRefused(): void
    {
        $employee = $this->insertUser();

        $this->expectException(PDOException::class);

        $this->insertVacation($employee, '2030-02-30', '2030-03-01');
    }

    public function testTheTablesUseInnoDbForTheirTransactionsAndForeignKeys(): void
    {
        $engines = $this->db->query(
            'SELECT table_name AS t, engine AS e FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (\'users\', \'vacations\')',
        );

        $this->assertCount(2, $engines);
        foreach ($engines as $table) {
            $this->assertSame('InnoDB', $table['e']);
        }
    }

    public function testTheQueriesThatServeTheAppHaveAnIndexToUse(): void
    {
        $plan = $this->db->query(
            'EXPLAIN SELECT id FROM vacations WHERE user_id = 1 AND status IN (\'pending\', \'approved\') AND `from` <= \'2030-07-10\' AND `to` >= \'2030-07-01\'',
        );

        $this->assertNotEmpty($plan[0]['key'] ?? null, 'The overlap query should use an index.');
    }
}

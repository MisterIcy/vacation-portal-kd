<?php

declare(strict_types=1);

namespace App\Shared;

use PDO;
use PDOException;
use Throwable;

class Database
{
    /** A transaction is tried this many times when it keeps colliding with another one. */
    private const int MAX_TRANSACTION_ATTEMPTS = 2;

    private PDO $pdo;

    /**
     * Run on every new connection (including automatic reconnects) so results do
     * not depend on server defaults: timestamps are read and written as UTC, and
     * invalid or oversized values are rejected instead of silently truncated.
     */
    public const string SESSION_INIT_SQL = "SET time_zone = '+00:00', "
        . "sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,"
        . "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'";

    /**
     * @param array<string, string> $config
     */
    public function __construct(array $config, ?PDO $connection = null)
    {
        if ($connection !== null) {
            // An existing connection (used by tests); the session settings above are MySQL's.
            $this->pdo = $connection;
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return;
        }

        try {
            $this->pdo = new PDO(
                self::buildDsn($config),
                $config['username'],
                $config['password'],
                [PDO::MYSQL_ATTR_INIT_COMMAND => self::SESSION_INIT_SQL],
            );
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // The driver message names the user and host, so keep it out of this
            // message (and anything that echoes it) and chain it for diagnostics.
            throw new \RuntimeException('Database connection failed', 0, $e);
        }
    }

    /**
     * @param array<string, string> $config
     */
    public static function buildDsn(array $config): string
    {
        $dsn = $config['driver'] . ':host=' . $config['host'];

        if (isset($config['port']) && $config['port'] !== '') {
            $dsn .= ';port=' . $config['port'];
        }

        return $dsn . ';dbname=' . $config['database'] . ';charset=' . $config['charset'];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var array<int, array<string, mixed>>|false $result */
        $result = $stmt->fetchAll();

        return $result === false ? [] : $result;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Runs the work in one transaction: committed when it returns, rolled back when it
     * throws. If another transaction gets in the way (a deadlock or a lock wait timeout)
     * nothing about this one was wrong, so it is run again once from the start; the work
     * must therefore be safe to repeat. Called inside a transaction it simply joins it.
     *
     * Keep it short and never wait on anything slow (such as another service) inside.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transactional(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }

        for ($attempt = 1; ; $attempt++) {
            $this->pdo->beginTransaction();

            try {
                $result = $work();
                $this->pdo->commit();

                return $result;
            } catch (Throwable $e) {
                $this->rollBackIfOpen();

                $canRetry = $e instanceof PDOException && DatabaseErrors::isRetryable($e);
                if (!$canRetry || $attempt >= self::MAX_TRANSACTION_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * After a deadlock MySQL has already rolled the transaction back, so there may be
     * nothing left to roll back.
     */
    private function rollBackIfOpen(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }
}

<?php

declare(strict_types=1);

namespace App\Shared;

use PDO;
use PDOException;

class Database
{
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
    public function __construct(array $config)
    {
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

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }
}

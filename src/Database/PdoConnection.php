<?php

namespace Pairity\Database;

use PDO;
use PDOException;
use Pairity\Contracts\ConnectionInterface;

class PdoConnection implements ConnectionInterface
{
    private PDO $pdo;
    /** @var array<string, \PDOStatement> */
    private array $stmtCache = [];
    private int $stmtCacheSize = 100; // LRU bound
    /** @var null|callable */
    private $queryLogger = null; // function(string $sql, array $params, float $ms): void

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** Enable/disable a bounded prepared statement cache (default size 100). */
    public function setStatementCacheSize(int $size): void
    {
        $this->stmtCacheSize = max(0, $size);
        if ($this->stmtCacheSize === 0) {
            $this->stmtCache = [];
        } else if (count($this->stmtCache) > $this->stmtCacheSize) {
            // trim
            $this->stmtCache = array_slice($this->stmtCache, -$this->stmtCacheSize, null, true);
        }
    }

    /** Set a logger callable to receive [sql, params, ms] for each query/execute. */
    public function setQueryLogger(?callable $logger): void
    {
        $this->queryLogger = $logger;
    }

    private function prepare(string $sql): \PDOStatement
    {
        if ($this->stmtCacheSize <= 0) {
            return $this->pdo->prepare($sql);
        }
        if (isset($this->stmtCache[$sql])) {
            // Touch for LRU by moving to end
            $stmt = $this->stmtCache[$sql];
            unset($this->stmtCache[$sql]);
            $this->stmtCache[$sql] = $stmt;
            return $stmt;
        }
        $stmt = $this->pdo->prepare($sql);
        $this->stmtCache[$sql] = $stmt;
        // Enforce LRU bound
        if (count($this->stmtCache) > $this->stmtCacheSize) {
            array_shift($this->stmtCache);
        }
        return $stmt;
    }

    public function query(string $sql, array $params = []): array
    {
        $t0 = microtime(true);
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($this->queryLogger) {
            $ms = (microtime(true) - $t0) * 1000.0;
            try { ($this->queryLogger)($sql, $params, $ms); } catch (\Throwable) {}
        }
        return $rows;
    }

    public function execute(string $sql, array $params = []): int
    {
        $t0 = microtime(true);
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        if ($this->queryLogger) {
            $ms = (microtime(true) - $t0) * 1000.0;
            try { ($this->queryLogger)($sql, $params, $ms); } catch (\Throwable) {}
        }
        return $count;
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getNative(): mixed
    {
        return $this->pdo;
    }

    public function lastInsertId(): ?string
    {
        try {
            return $this->pdo->lastInsertId() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

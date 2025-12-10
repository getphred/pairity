<?php

namespace Pairity\Orm;

use Closure;

/**
 * Opt-in Unit of Work (MVP):
 * - Ambient/current context with begin()/run()/current()
 * - Identity Map per DAO class + primary key (string)
 * - Deferred operation queues grouped by connection object
 * - commit() opens transactions/sessions per connection and executes queued ops
 */
final class UnitOfWork
{
    /** @var UnitOfWork|null */
    private static ?UnitOfWork $current = null;
    /** @var bool */
    private static bool $suspended = false; // when true, DAOs should execute immediately

    /** @var array<string, array<string, object>> map[daoClass][id] = DTO */
    private array $identityMap = [];

    /**
     * Queues grouped by a connection hash key.
     * Each entry: ['conn' => object, 'ops' => list<Closure>]
     *
     * @var array<string, array{conn:object, ops:array<int,Closure>}> 
     */
    private array $queues = [];

    private function __construct() {}

    public static function begin(): UnitOfWork
    {
        if (self::$current !== null) {
            return self::$current;
        }
        self::$current = new UnitOfWork();
        return self::$current;
    }

    /**
     * Run a Unit of Work and automatically commit or rollback on exception.
     * @template T
     * @param Closure(UnitOfWork):T $callback
     * @return mixed
     */
    public static function run(Closure $callback): mixed
    {
        $uow = self::begin();
        try {
            $result = $callback($uow);
            $uow->commit();
            return $result;
        } catch (\Throwable $e) {
            $uow->rollback();
            throw $e;
        }
    }

    public static function current(): ?UnitOfWork
    {
        return self::$current;
    }

    /** Temporarily suspend UoW interception so DAOs execute immediately within the callable. */
    public static function suspendDuring(Closure $cb): mixed
    {
        $prev = self::$suspended;
        self::$suspended = true;
        try { return $cb(); } finally { self::$suspended = $prev; }
    }

    public static function isSuspended(): bool
    {
        return self::$suspended;
    }

    // ===== Identity Map =====

    /** Attach a DTO to identity map. */
    public function attach(string $daoClass, string $id, object $dto): void
    {
        $this->identityMap[$daoClass][$id] = $dto;
    }

    /** Fetch an attached DTO if present. */
    public function get(string $daoClass, string $id): ?object
    {
        return $this->identityMap[$daoClass][$id] ?? null;
    }

    // ===== Defer operations =====

    /** Enqueue a mutation for the given connection object. */
    public function enqueue(object $connection, Closure $operation): void
    {
        $key = spl_object_hash($connection);
        if (!isset($this->queues[$key])) {
            $this->queues[$key] = ['conn' => $connection, 'ops' => []];
        }
        $this->queues[$key]['ops'][] = $operation;
    }

    /** Execute all queued operations per connection within a transaction/session. */
    public function commit(): void
    {
        // Ensure we run ops with DAO interception suspended to avoid re-enqueue
        self::suspendDuring(function () {
            // Grouped by connection type
            foreach ($this->queues as $entry) {
                $conn = $entry['conn'];
                $ops = $entry['ops'];
                // PDO/SQL path: has transaction(callable)
                if (method_exists($conn, 'transaction')) {
                    $conn->transaction(function () use ($ops) {
                        foreach ($ops as $op) { $op(); }
                        return null;
                    });
                }
                // Mongo path: try withTransaction first, then withSession, else run directly
                elseif (method_exists($conn, 'withTransaction')) {
                    $conn->withTransaction(function () use ($ops) {
                        foreach ($ops as $op) { $op(); }
                        return null;
                    });
                } elseif (method_exists($conn, 'withSession')) {
                    $conn->withSession(function () use ($ops) {
                        foreach ($ops as $op) { $op(); }
                        return null;
                    });
                } else {
                    // Fallback: no transaction API; just run
                    foreach ($ops as $op) { $op(); }
                }
            }
        });

        // Clear queues after successful commit
        $this->queues = [];
        // Do not clear identity map by default; keep for the scope
        // Close UoW scope
        self::$current = null;
    }

    /** Rollback just clears queues and current context; actual rollback is handled by transactions when run. */
    public function rollback(): void
    {
        $this->queues = [];
        self::$current = null;
    }
}

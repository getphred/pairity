<?php

namespace Pairity\Orm;

use Closure;
use Pairity\Model\AbstractDao as SqlDao;
use Pairity\NoSql\Mongo\AbstractMongoDao as MongoDao;
use Pairity\Events\Events;

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
    /** @var array<string, array<string, array<string,mixed>>> snapshots[daoClass][id] = array representation */
    private array $snapshots = [];
    /** @var array<string, array<string, object>> daoBind[daoClass][id] = DAO instance for updates */
    private array $daoBind = [];
    /** Enable snapshot diffing */
    private bool $snapshotsEnabled = false;

    /**
     * Queues grouped by a connection hash key.
     * Each entry: ['conn' => object, 'ops' => list<array{op:Closure, meta:array<string,mixed>}>]
     * meta keys (MVP):
     *  - type: 'update'|'delete'|'raw'
     *  - mode: 'byId'|'byCriteria'|'raw'
     *  - dao: object (DAO instance)
     *  - id: string (for byId)
     *  - criteria: array (for byCriteria)
     *
     * @var array<string, array{conn:object, ops:array<int,array{op:Closure,meta:array<string,mixed>}>}>
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
        if ($this->snapshotsEnabled) {
            // store shallow snapshot
            $arr = [];
            try {
                if (method_exists($dto, 'toArray')) {
                    /** @var array<string,mixed> $arr */
                    $arr = $dto->toArray(false);
                }
            } catch (\Throwable) { $arr = []; }
            $this->snapshots[$daoClass][$id] = is_array($arr) ? $arr : [];
        }
    }

    /** Fetch an attached DTO if present. */
    public function get(string $daoClass, string $id): ?object
    {
        return $this->identityMap[$daoClass][$id] ?? null;
    }

    /** Bind a DAO instance to a managed entity for potential snapshot diff updates. */
    public function bindDao(string $daoClass, string $id, object $dao): void
    {
        $this->daoBind[$daoClass][$id] = $dao;
    }

    /** Enable/disable snapshot diffing. */
    public function enableSnapshots(bool $flag = true): static
    {
        $this->snapshotsEnabled = $flag;
        return $this;
    }

    public function isManaged(string $daoClass, string $id): bool
    {
        return isset($this->identityMap[$daoClass][$id]);
    }

    public function detach(string $daoClass, string $id): void
    {
        unset($this->identityMap[$daoClass][$id], $this->snapshots[$daoClass][$id], $this->daoBind[$daoClass][$id]);
    }

    public function clear(): void
    {
        $this->identityMap = [];
        $this->snapshots = [];
        $this->daoBind = [];
    }

    // ===== Defer operations =====

    /** Enqueue a mutation for the given connection object (back-compat, raw op). */
    public function enqueue(object $connection, Closure $operation): void
    {
        $key = spl_object_hash($connection);
        if (!isset($this->queues[$key])) {
            $this->queues[$key] = ['conn' => $connection, 'ops' => []];
        }
        $this->queues[$key]['ops'][] = ['op' => $operation, 'meta' => ['type' => 'raw', 'mode' => 'raw']];
    }

    /** Enqueue a mutation with metadata for relation-aware ordering/cascades. */
    public function enqueueWithMeta(object $connection, array $meta, Closure $operation): void
    {
        $key = spl_object_hash($connection);
        if (!isset($this->queues[$key])) {
            $this->queues[$key] = ['conn' => $connection, 'ops' => []];
        }
        $this->queues[$key]['ops'][] = ['op' => $operation, 'meta' => $meta];
    }

    /** Execute all queued operations per connection within a transaction/session. */
    public function commit(): void
    {
        // Ensure we run ops with DAO interception suspended to avoid re-enqueue
        self::suspendDuring(function () {
            // uow.beforeCommit
            try { $payload = ['context' => 'uow']; Events::dispatcher()->dispatch('uow.beforeCommit', $payload); } catch (\Throwable) {}
            // Grouped by connection type
            foreach ($this->queues as $entry) {
                $conn = $entry['conn'];
                $ops = $this->expandAndOrder($entry['ops']);
                // Inject snapshot-based updates for managed entities with diffs
                $ops = $this->injectSnapshotDiffUpdates($ops);
                // Coalesce multiple updates for the same entity and order update-before-delete
                $ops = $this->coalesceAndOrderPerEntity($ops);
                // PDO/SQL path: has transaction(callable)
                if (method_exists($conn, 'transaction')) {
                    $conn->transaction(function () use ($ops) {
                        foreach ($ops as $o) { ($o['op'])(); }
                        return null;
                    });
                }
                // Mongo path: try withTransaction first, then withSession, else run directly
                elseif (method_exists($conn, 'withTransaction')) {
                    $conn->withTransaction(function () use ($ops) {
                        foreach ($ops as $o) { ($o['op'])(); }
                        return null;
                    });
                } elseif (method_exists($conn, 'withSession')) {
                    $conn->withSession(function () use ($ops) {
                        foreach ($ops as $o) { ($o['op'])(); }
                        return null;
                    });
                } else {
                    // Fallback: no transaction API; just run
                    foreach ($ops as $o) { ($o['op'])(); }
                }
            }
            // uow.afterCommit
            try { $payload2 = ['context' => 'uow']; Events::dispatcher()->dispatch('uow.afterCommit', $payload2); } catch (\Throwable) {}
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

    /**
     * Expand cascades and order ops so child deletes run before parent deletes.
     * @param array<int,array{op:Closure,meta:array<string,mixed>}> $ops
     * @return array<int,array{op:Closure,meta:array<string,mixed>}> ordered ops
     */
    private function expandAndOrder(array $ops): array
    {
        $expanded = [];
        foreach ($ops as $o) {
            $meta = $o['meta'] ?? [];
            // Detect deleteById on a DAO with cascade-enabled relations
            if (($meta['type'] ?? '') === 'delete' && ($meta['mode'] ?? '') === 'byId' && isset($meta['dao']) && is_object($meta['dao'])) {
                $dao = $meta['dao'];
                $parentId = (string)($meta['id'] ?? '');
                if ($parentId !== '') {
                    // Determine relations and cascade flags
                    $rels = $this->readRelations($dao);
                    foreach ($rels as $name => $cfg) {
                        $type = (string)($cfg['type'] ?? '');
                        $cascade = false;
                        if (isset($cfg['cascadeDelete'])) {
                            $cascade = (bool)$cfg['cascadeDelete'];
                        } elseif (isset($cfg['cascade']['delete'])) {
                            $cascade = (bool)$cfg['cascade']['delete'];
                        }
                        if (!$cascade) { continue; }
                        if ($type === 'hasMany' || $type === 'hasOne') {
                            $childDaoClass = $cfg['dao'] ?? null;
                            $foreignKey = (string)($cfg['foreignKey'] ?? '');
                            $localKey = (string)($cfg['localKey'] ?? 'id');
                            if (!is_string($childDaoClass) || $foreignKey === '') { continue; }
                            // Instantiate child DAO sharing same connection
                            try {
                                /** @var object $childDao */
                                $childDao = new $childDaoClass($dao->getConnection());
                            } catch (\Throwable) {
                                continue;
                            }
                            // Create a child delete op to run before parent
                            $childOp = function () use ($childDao, $foreignKey, $parentId) {
                                self::suspendDuring(function () use ($childDao, $foreignKey, $parentId) {
                                    // delete children by FK
                                    if ($childDao instanceof SqlDao) {
                                        $childDao->deleteBy([$foreignKey => $parentId]);
                                    } elseif ($childDao instanceof MongoDao) {
                                        $childDao->deleteBy([$foreignKey => $parentId]);
                                    }
                                });
                            };
                            $expanded[] = ['op' => $childOp, 'meta' => ['type' => 'delete', 'mode' => 'byCriteria', 'dao' => $childDao]];
                        }
                    }
                }
            }
            // Then the original op
            $expanded[] = $o;
        }

        // Basic stable order is fine since cascades were inserted before parent.
        return $expanded;
    }

    /**
     * Inject snapshot-based updates for managed entities that have changed but were not explicitly updated.
     * Only when snapshots are enabled.
     * @param array<int,array{op:Closure,meta:array<string,mixed>}> $ops
     * @return array<int,array{op:Closure,meta:array<string,mixed>}> $ops
     */
    private function injectSnapshotDiffUpdates(array $ops): array
    {
        if (!$this->snapshotsEnabled) {
            return $ops;
        }
        // Build a set of entities already scheduled for update/delete
        $scheduled = [];
        foreach ($ops as $o) {
            $m = $o['meta'] ?? [];
            if (!isset($m['dao']) || !is_object($m['dao'])) continue;
            $daoClass = get_class($m['dao']);
            $id = (string)($m['id'] ?? '');
            if ($id !== '') {
                $scheduled[$daoClass][$id] = true;
            }
        }
        // For each managed entity, if changed and not scheduled, enqueue update
        foreach ($this->identityMap as $daoClass => $entities) {
            foreach ($entities as $id => $dto) {
                if (!$this->snapshotsEnabled) break;
                $snap = $this->snapshots[$daoClass][$id] ?? null;
                if ($snap === null) continue;
                $now = [];
                try { if (method_exists($dto, 'toArray')) { $now = $dto->toArray(false); } } catch (\Throwable) { $now = []; }
                if (!is_array($now)) $now = [];
                $diff = $this->diffAssoc($snap, $now);
                if (!$diff) continue;
                if (isset($scheduled[$daoClass][$id])) continue;
                $dao = $this->daoBind[$daoClass][$id] ?? null;
                if (!$dao) continue;
                // Build op that performs immediate update under suspension
                $op = function () use ($dao, $id, $diff) {
                    self::suspendDuring(function () use ($dao, $id, $diff) {
                        if (method_exists($dao, 'update')) { $dao->update($id, $diff); }
                    });
                };
                $ops[] = ['op' => $op, 'meta' => ['type' => 'update', 'mode' => 'byId', 'dao' => $dao, 'id' => (string)$id, 'payload' => $diff]];
            }
        }
        return $ops;
    }

    /**
     * Merge multiple updates for the same entity and ensure update happens before delete for that entity.
     * @param array<int,array{op:Closure,meta:array<string,mixed>}> $ops
     * @return array<int,array{op:Closure,meta:array<string,mixed>}> $ops
     */
    private function coalesceAndOrderPerEntity(array $ops): array
    {
        // Group updates by daoClass+id
        $updateMap = [];
        $deleteSet = [];
        foreach ($ops as $o) {
            $m = $o['meta'] ?? [];
            if (!isset($m['dao']) || !is_object($m['dao'])) continue;
            $dao = $m['dao'];
            $daoClass = get_class($dao);
            $id = (string)($m['id'] ?? '');
            if (($m['type'] ?? '') === 'update' && ($m['mode'] ?? '') === 'byId' && $id !== '') {
                $key = $daoClass . '#' . $id;
                $payload = (array)($m['payload'] ?? []);
                if (!isset($updateMap[$key])) { $updateMap[$key] = ['dao' => $dao, 'id' => $id, 'payload' => []]; }
                // merge (last write wins)
                $updateMap[$key]['payload'] = array_merge($updateMap[$key]['payload'], $payload);
            }
            if (($m['type'] ?? '') === 'delete' && ($m['mode'] ?? '') === 'byId' && $id !== '') {
                $deleteSet[$daoClass . '#' . $id] = ['dao' => $dao, 'id' => $id];
            }
        }

        if (!$updateMap) { return $ops; }

        // Rebuild ops: for each original op, skip individual updates; add one merged update before a delete for the same entity
        $result = [];
        $emittedUpdate = [];
        foreach ($ops as $o) {
            $m = $o['meta'] ?? [];
            $emit = true;
            $dao = $m['dao'] ?? null;
            $daoClass = is_object($dao) ? get_class($dao) : null;
            $id = (string)($m['id'] ?? '');
            $key = ($daoClass && $id !== '') ? ($daoClass . '#' . $id) : null;
            if (($m['type'] ?? '') === 'update' && ($m['mode'] ?? '') === 'byId' && $key && isset($updateMap[$key])) {
                // skip individual update; we'll emit merged one once
                $emit = false;
            }
            if (($m['type'] ?? '') === 'delete' && ($m['mode'] ?? '') === 'byId' && $key && isset($updateMap[$key]) && !isset($emittedUpdate[$key])) {
                // emit merged update before delete
                $merged = $updateMap[$key];
                $updOp = function () use ($merged) {
                    self::suspendDuring(function () use ($merged) {
                        $dao = $merged['dao']; $id = $merged['id']; $payload = $merged['payload'];
                        if (method_exists($dao, 'update')) { $dao->update($id, $payload); }
                    });
                };
                $result[] = ['op' => $updOp, 'meta' => ['type' => 'update', 'mode' => 'byId', 'dao' => $merged['dao'], 'id' => (string)$merged['id'], 'payload' => $merged['payload']]];
                $emittedUpdate[$key] = true;
            }
            if ($emit) {
                $result[] = $o;
            }
        }
        // For any remaining merged updates not emitted (no delete op), append them at the end
        foreach ($updateMap as $k => $merged) {
            if (isset($emittedUpdate[$k])) continue;
            $updOp = function () use ($merged) {
                self::suspendDuring(function () use ($merged) {
                    $dao = $merged['dao']; $id = $merged['id']; $payload = $merged['payload'];
                    if (method_exists($dao, 'update')) { $dao->update($id, $payload); }
                });
            };
            $result[] = ['op' => $updOp, 'meta' => ['type' => 'update', 'mode' => 'byId', 'dao' => $merged['dao'], 'id' => (string)$merged['id'], 'payload' => $merged['payload']]];
        }
        return $result;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function diffAssoc(array $a, array $b): array
    {
        $diff = [];
        foreach ($b as $k => $v) {
            // only simple scalar/array comparisons; nested DTOs are out of scope
            $av = $a[$k] ?? null;
            if ($av !== $v) {
                $diff[$k] = $v;
            }
        }
        // Skip keys present in $a but removed in $b to avoid unintended nulling
        return $diff;
    }

    /**
     * Read relations metadata from DAO instance if available.
     * @return array<string,mixed>
     */
    private function readRelations(object $dao): array
    {
        // Prefer a public relationMap() accessor if provided
        if (method_exists($dao, 'relationMap')) {
            try { $rels = $dao->relationMap(); if (is_array($rels)) return $rels; } catch (\Throwable) {}
        }
        // Fallback: try calling protected relations() via reflection
        try {
            $ref = new \ReflectionObject($dao);
            if ($ref->hasMethod('relations')) {
                $m = $ref->getMethod('relations');
                $m->setAccessible(true);
                $rels = $m->invoke($dao);
                if (is_array($rels)) return $rels;
            }
        } catch (\Throwable) {}
        return [];
    }
}

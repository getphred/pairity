<?php

namespace Pairity\Model;

use Pairity\Contracts\ConnectionInterface;
use Pairity\Contracts\DaoInterface;
use Pairity\Orm\UnitOfWork;
use Pairity\Model\Casting\CasterInterface;
use Pairity\Events\Events;

abstract class AbstractDao implements DaoInterface
{
    protected ConnectionInterface $connection;
    protected string $primaryKey = 'id';
    /** @var array<int,string>|null */
    private ?array $selectedFields = null;
    /** @var array<string, array<int,string>> */
    private array $relationFields = [];
    /** @var array<int,string> */
    private array $with = [];
    /**
     * Nested eager-loading tree built from with() calls.
     * Example: with(['posts.comments','user']) =>
     *   [ 'posts' => ['comments' => []], 'user' => [] ]
     * @var array<string, array<string,mixed>>
     */
    private array $withTree = [];
    /**
     * Per relation (and nested path) constraints.
     * Keys are relation paths like 'posts' or 'posts.comments'.
     * Values are callables taking the related DAO instance.
     * @var array<string, callable>
     */
    private array $withConstraints = [];
    /**
     * Optional per‑relation eager loading strategies for first‑level relations.
     * Keys are relation names; values like 'join'.
     * @var array<string,string>
     */
    private array $withStrategies = [];
    /** Soft delete include flags */
    private bool $includeTrashed = false;
    private bool $onlyTrashed = false;
    /** @var array<int, callable> */
    private array $runtimeScopes = [];
    /** @var array<string, callable> */
    private array $namedScopes = [];
    /**
     * Optional eager loading strategy for next find* call.
     * null (default) uses the portable subquery/batched IN strategy.
     * 'join' opts in to join-based eager loading for supported SQL relations (single level).
     */
    private ?string $eagerStrategy = null;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    abstract public function getTable(): string;
    /**
     * The DTO class this DAO hydrates.
     * @return class-string<AbstractDto>
     */
    abstract protected function dtoClass(): string;

    /**
     * Relation metadata to enable eager/lazy loading.
     * @return array<string, array<string, mixed>>
     */
    protected function relations(): array
    {
        return [];
    }

    /**
     * Optional schema metadata for this DAO (MVP).
     * Example structure:
     * return [
     *   'primaryKey' => 'id',
     *   'columns' => [
     *      'id' => ['cast' => 'int'],
     *      'email' => ['cast' => 'string'],
     *      'data' => ['cast' => 'json'],
     *   ],
     *   'timestamps' => ['createdAt' => 'created_at', 'updatedAt' => 'updated_at'],
     *   'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
     * ];
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [];
    }

    public function getPrimaryKey(): string
    {
        $schema = $this->getSchema();
        if (isset($schema['primaryKey']) && is_string($schema['primaryKey']) && $schema['primaryKey'] !== '') {
            return $schema['primaryKey'];
        }
        return $this->primaryKey;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Opt-in to join-based eager loading for the next find* call (SQL only, single-level relations).
     */
    public function useJoinEager(): static
    {
        $this->eagerStrategy = 'join';
        return $this;
    }

    /**
     * Set eager strategy explicitly: 'join' or 'subquery'. Resets after next find* call.
     */
    public function eagerStrategy(string $strategy): static
    {
        $strategy = strtolower($strategy);
        $this->eagerStrategy = in_array($strategy, ['join', 'subquery'], true) ? $strategy : null;
        return $this;
    }

    /** @param array<string,mixed> $criteria */
    public function findOneBy(array $criteria): ?AbstractDto
    {
        // Events: dao.beforeFind (criteria may be mutated)
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => &$criteria]; Events::dispatcher()->dispatch('dao.beforeFind', $ev); } catch (\Throwable) {}
        $criteria = $this->applyDefaultScopes($criteria);
        $this->applyRuntimeScopesToCriteria($criteria);
        [$where, $bindings] = $this->buildWhere($criteria);
        $where = $this->appendScopedWhere($where);
        $dto = null;
        if ($this->with && $this->shouldUseJoinEager()) {
            [$sql, $bindings2, $meta] = $this->buildJoinSelect($where, $bindings, 1, 0);
            $rows = $this->connection->query($sql, $bindings2);
            $list = $this->hydrateFromJoinRows($rows, $meta);
            $dto = $list[0] ?? null;
        } else {
            $sql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable() . ($where ? ' WHERE ' . $where : '') . ' LIMIT 1';
            $rows = $this->connection->query($sql, $bindings);
            $dto = isset($rows[0]) ? $this->hydrate($this->castRowFromStorage($rows[0])) : null;
            if ($dto && $this->with) {
                $this->attachRelations([$dto]);
            }
        }
        $this->resetFieldSelections();
        $this->resetRuntimeScopes();
        $this->eagerStrategy = null; // reset
        $this->withStrategies = [];
        // Events: dao.afterFind
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $dto]; Events::dispatcher()->dispatch('dao.afterFind', $payload); } catch (\Throwable) {}
        return $dto;
    }

    public function findById(int|string $id): ?AbstractDto
    {
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            $managed = $uow->get(static::class, (string)$id);
            if ($managed instanceof AbstractDto) {
                return $managed;
            }
        }
        return $this->findOneBy([$this->getPrimaryKey() => $id]);
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int, AbstractDto>
     */
    public function findAllBy(array $criteria = []): array
    {
        // Events: dao.beforeFind (criteria may be mutated)
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => &$criteria]; Events::dispatcher()->dispatch('dao.beforeFind', $ev); } catch (\Throwable) {}
        $criteria = $this->applyDefaultScopes($criteria);
        $this->applyRuntimeScopesToCriteria($criteria);
        [$where, $bindings] = $this->buildWhere($criteria);
        $where = $this->appendScopedWhere($where);
        if ($this->with && $this->shouldUseJoinEager()) {
            [$sql2, $bindings2, $meta] = $this->buildJoinSelect($where, $bindings, null, null);
            $rows = $this->connection->query($sql2, $bindings2);
            $dtos = $this->hydrateFromJoinRows($rows, $meta);
        } else {
            $sql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable() . ($where ? ' WHERE ' . $where : '');
            $rows = $this->connection->query($sql, $bindings);
            $dtos = array_map(fn($r) => $this->hydrate($this->castRowFromStorage($r)), $rows);
            if ($dtos && $this->with) {
                $this->attachRelations($dtos);
            }
        }
        $this->resetFieldSelections();
        $this->resetRuntimeScopes();
        $this->eagerStrategy = null; // reset
        $this->withStrategies = [];
        // Events: dao.afterFind (list)
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dtos' => $dtos]; Events::dispatcher()->dispatch('dao.afterFind', $payload); } catch (\Throwable) {}
        return $dtos;
    }

    /**
     * Paginate results for the given criteria.
     * @return array{data:array<int,AbstractDto>,total:int,perPage:int,currentPage:int,lastPage:int}
     */
    public function paginate(int $page, int $perPage = 15, array $criteria = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $criteria = $this->applyDefaultScopes($criteria);
        $this->applyRuntimeScopesToCriteria($criteria);
        [$where, $bindings] = $this->buildWhere($criteria);
        $whereFinal = $this->appendScopedWhere($where);

        // Total
        $countSql = 'SELECT COUNT(*) AS cnt FROM ' . $this->getTable() . ($whereFinal ? ' WHERE ' . $whereFinal : '');
        $countRows = $this->connection->query($countSql, $bindings);
        $total = (int)($countRows[0]['cnt'] ?? 0);

        // Page data
        $offset = ($page - 1) * $perPage;
        $dataSql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable()
            . ($whereFinal ? ' WHERE ' . $whereFinal : '')
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $rows = $this->connection->query($dataSql, $bindings);
        $dtos = array_map(fn($r) => $this->hydrate($this->castRowFromStorage($r)), $rows);
        if ($dtos && $this->with) {
            $this->attachRelations($dtos);
        }
        $this->resetFieldSelections();
        $this->resetRuntimeScopes();
        $this->eagerStrategy = null; // reset
        $this->withStrategies = [];

        $lastPage = (int)max(1, (int)ceil($total / $perPage));
        return [
            'data' => $dtos,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => $lastPage,
        ];
    }

    /** Simple pagination without total count. Returns nextPage if there might be more. */
    public function simplePaginate(int $page, int $perPage = 15, array $criteria = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $criteria = $this->applyDefaultScopes($criteria);
        $this->applyRuntimeScopesToCriteria($criteria);
        [$where, $bindings] = $this->buildWhere($criteria);
        $whereFinal = $this->appendScopedWhere($where);

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable()
            . ($whereFinal ? ' WHERE ' . $whereFinal : '')
            . ' LIMIT ' . ($perPage + 1) . ' OFFSET ' . $offset; // fetch one extra to detect more
        $rows = $this->connection->query($sql, $bindings);
        $hasMore = count($rows) > $perPage;
        if ($hasMore) { array_pop($rows); }
        $dtos = array_map(fn($r) => $this->hydrate($this->castRowFromStorage($r)), $rows);
        if ($dtos && $this->with) { $this->attachRelations($dtos); }
        $this->resetFieldSelections();
        $this->resetRuntimeScopes();
        $this->eagerStrategy = null; // reset
        $this->withStrategies = [];

        return [
            'data' => $dtos,
            'perPage' => $perPage,
            'currentPage' => $page,
            'nextPage' => $hasMore ? $page + 1 : null,
        ];
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): AbstractDto
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('insert() requires non-empty data');
        }
        // Events: dao.beforeInsert (allow mutation of $data)
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'data' => &$data]; Events::dispatcher()->dispatch('dao.beforeInsert', $ev); } catch (\Throwable) {}
        $data = $this->prepareForInsert($data);
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ' . $this->getTable() . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->connection->execute($sql, $data);
        $id = $this->connection->lastInsertId();
        $pk = $this->getPrimaryKey();
        if ($id !== null) {
            $dto = $this->findById($id) ?? $this->hydrate(array_merge($data, [$pk => $id]));
            try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $dto]; Events::dispatcher()->dispatch('dao.afterInsert', $payload); } catch (\Throwable) {}
            return $dto;
        }
        // Fallback when lastInsertId is unavailable: return hydrated DTO from provided data
        $dto = $this->hydrate($this->castRowFromStorage($data));
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $dto]; Events::dispatcher()->dispatch('dao.afterInsert', $payload); } catch (\Throwable) {}
        return $dto;
    }

    /** @param array<string,mixed> $data */
    public function update(int|string $id, array $data): AbstractDto
    {
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            // Defer execution; return a synthesized DTO
            $existing = $this->findById($id);
            if (!$existing && empty($data)) {
                throw new \InvalidArgumentException('No data provided to update and record not found');
            }
            // Events: dao.beforeUpdate (mutate $data)
            try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'id' => $id, 'data' => &$data]; Events::dispatcher()->dispatch('dao.beforeUpdate', $ev); } catch (\Throwable) {}
            $toStore = $this->prepareForUpdate($data);
            $self = $this;
            $conn = $this->connection;
            $uow->enqueueWithMeta($conn, [
                'type' => 'update',
                'mode' => 'byId',
                'dao'  => $this,
                'id'   => (string)$id,
                'payload' => $toStore,
            ], function () use ($self, $id, $toStore) {
                UnitOfWork::suspendDuring(function () use ($self, $id, $toStore) {
                    $self->doImmediateUpdateWithLock($id, $toStore);
                });
            });
            $base = $existing ? $existing->toArray(false) : [];
            $pk = $this->getPrimaryKey();
            $result = array_merge($base, $data, [$pk => $id]);
            $dto = $this->hydrate($this->castRowFromStorage($result));
            try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $dto]; Events::dispatcher()->dispatch('dao.afterUpdate', $payload); } catch (\Throwable) {}
            return $dto;
        }

        if (empty($data)) {
            $existing = $this->findById($id);
            if ($existing) return $existing;
            throw new \InvalidArgumentException('No data provided to update and record not found');
        }
        // Events: dao.beforeUpdate
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'id' => $id, 'data' => &$data]; Events::dispatcher()->dispatch('dao.beforeUpdate', $ev); } catch (\Throwable) {}
        $data = $this->prepareForUpdate($data);
        $this->doImmediateUpdateWithLock($id, $data);
        $updated = $this->findById($id);
        if ($updated === null) {
            $pk = $this->getPrimaryKey();
            $dto = $this->hydrate($this->castRowFromStorage(array_merge($data, [$pk => $id])));
            try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $dto]; Events::dispatcher()->dispatch('dao.afterUpdate', $payload); } catch (\Throwable) {}
            return $dto;
        }
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'dto' => $updated]; Events::dispatcher()->dispatch('dao.afterUpdate', $payload); } catch (\Throwable) {}
        return $updated;
    }

    public function deleteById(int|string $id): int
    {
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            $self = $this; $conn = $this->connection; $theId = $id;
            try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'id' => $id]; Events::dispatcher()->dispatch('dao.beforeDelete', $ev); } catch (\Throwable) {}
            $uow->enqueueWithMeta($conn, [
                'type' => 'delete',
                'mode' => 'byId',
                'dao'  => $this,
                'id'   => (string)$id,
            ], function () use ($self, $theId) {
                UnitOfWork::suspendDuring(function () use ($self, $theId) { $self->deleteById($theId); });
            });
            // deferred; immediate affected count unknown
            return 0;
        }
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'id' => $id]; Events::dispatcher()->dispatch('dao.beforeDelete', $ev); } catch (\Throwable) {}
        if ($this->hasSoftDeletes()) {
            $columns = $this->softDeleteConfig();
            $deletedAt = $columns['deletedAt'] ?? 'deleted_at';
            $now = $this->nowString();
            $sql = 'UPDATE ' . $this->getTable() . " SET {$deletedAt} = :ts WHERE " . $this->getPrimaryKey() . ' = :pk';
            return $this->connection->execute($sql, ['ts' => $now, 'pk' => $id]);
        }
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE ' . $this->getPrimaryKey() . ' = :pk';
        $affected = $this->connection->execute($sql, ['pk' => $id]);
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'id' => $id, 'affected' => $affected]; Events::dispatcher()->dispatch('dao.afterDelete', $payload); } catch (\Throwable) {}
        return $affected;
    }

    /** @param array<string,mixed> $criteria */
    public function deleteBy(array $criteria): int
    {
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            $self = $this; $conn = $this->connection; $crit = $criteria;
            try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => &$criteria]; Events::dispatcher()->dispatch('dao.beforeDelete', $ev); } catch (\Throwable) {}
            $uow->enqueueWithMeta($conn, [
                'type' => 'delete',
                'mode' => 'byCriteria',
                'dao'  => $this,
                'criteria' => $criteria,
            ], function () use ($self, $crit) {
                UnitOfWork::suspendDuring(function () use ($self, $crit) { $self->deleteBy($crit); });
            });
            return 0;
        }
        try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => &$criteria]; Events::dispatcher()->dispatch('dao.beforeDelete', $ev); } catch (\Throwable) {}
        if ($this->hasSoftDeletes()) {
            [$where, $bindings] = $this->buildWhere($criteria);
            if ($where === '') { return 0; }
            $columns = $this->softDeleteConfig();
            $deletedAt = $columns['deletedAt'] ?? 'deleted_at';
            $now = $this->nowString();
            $sql = 'UPDATE ' . $this->getTable() . " SET {$deletedAt} = :ts WHERE " . $where;
            $bindings = array_merge(['ts' => $now], $bindings);
            return $this->connection->execute($sql, $bindings);
        }
        [$where, $bindings] = $this->buildWhere($criteria);
        if ($where === '') { return 0; }
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE ' . $where;
        $affected = $this->connection->execute($sql, $bindings);
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => $criteria, 'affected' => $affected]; Events::dispatcher()->dispatch('dao.afterDelete', $payload); } catch (\Throwable) {}
        return $affected;
    }

    /**
     * Update rows matching the given criteria with the provided data.
     *
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $data
     */
    public function updateBy(array $criteria, array $data): int
    {
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            if (empty($data)) { return 0; }
            // Events: dao.beforeUpdate (bulk)
            try { $ev = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => &$criteria, 'data' => &$data]; Events::dispatcher()->dispatch('dao.beforeUpdate', $ev); } catch (\Throwable) {}
            $self = $this; $conn = $this->connection; $crit = $criteria; $payload = $this->prepareForUpdate($data);
            $uow->enqueueWithMeta($conn, [
                'type' => 'update',
                'mode' => 'byCriteria',
                'dao'  => $this,
                'criteria' => $criteria,
            ], function () use ($self, $crit, $payload) {
                UnitOfWork::suspendDuring(function () use ($self, $crit, $payload) { $self->updateBy($crit, $payload); });
            });
            // unknown affected rows until commit
            return 0;
        }
        if (empty($data)) {
            return 0;
        }
        // Optimistic locking note: bulk updates under optimistic locking are not supported
        if ($this->hasOptimisticLocking()) {
            throw new \Pairity\Orm\OptimisticLockException('Optimistic locking enabled: use update(id, ...) instead of bulk updateBy(...)');
        }
        [$where, $whereBindings] = $this->buildWhere($criteria);
        if ($where === '') {
            return 0;
        }
        // Ensure timestamps and storage casts are applied consistently with update()
        $data = $this->prepareForUpdate($data);
        $sets = [];
        $setParams = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = :set_$col";
            $setParams["set_$col"] = $val;
        }

        $sql = 'UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $affected = $this->connection->execute($sql, array_merge($setParams, $whereBindings));
        try { $payload = ['dao' => $this, 'table' => $this->getTable(), 'criteria' => $criteria, 'affected' => $affected]; Events::dispatcher()->dispatch('dao.afterUpdate', $payload); } catch (\Throwable) {}
        return $affected;
    }

    /** Expose relation metadata for UoW ordering/cascades. */
    public function relationMap(): array
    {
        return $this->relations();
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function buildWhere(array $criteria): array
    {
        if (!$criteria) {
            return ['', []];
        }
        $parts = [];
        $bindings = [];
        foreach ($criteria as $col => $val) {
            $param = 'w_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$col);
            if ($val === null) {
                $parts[] = "$col IS NULL";
            } else {
                $parts[] = "$col = :$param";
                $bindings[$param] = $val;
            }
        }
        return [implode(' AND ', $parts), $bindings];
    }

    /**
     * Fetch all rows where a column is within the given set of values.
     *
     * @param string $column
     * @param array<int, int|string> $values
     * @return array<int, array<string,mixed>>
     */
    /**
     * Fetch related rows where a column is within a set of values.
     * Returns DTOs.
     *
     * @param string $column
     * @param array<int, int|string> $values
     * @param array<int,string>|null $selectFields If provided, use these fields instead of the DAO's current selection
     * @return array<int, AbstractDto>
     */
    public function findAllWhereIn(string $column, array $values, ?array $selectFields = null): array
    {
        if (empty($values)) {
            return [];
        }
        $values = array_values(array_unique($values, SORT_REGULAR));
        $placeholders = [];
        $bindings = [];
        foreach ($values as $i => $val) {
            $ph = "in_{$i}";
            $placeholders[] = ":{$ph}";
            $bindings[$ph] = $val;
        }
        $selectList = $selectFields && $selectFields !== ['*']
            ? implode(', ', $selectFields)
            : $this->selectList();
        $where = $column . ' IN (' . implode(', ', $placeholders) . ')';
        $where = $this->appendScopedWhere($where);
        $sql = 'SELECT ' . $selectList . ' FROM ' . $this->getTable() . ' WHERE ' . $where;
        $rows = $this->connection->query($sql, $bindings);
        return array_map(fn($r) => $this->hydrate($this->castRowFromStorage($r)), $rows);
    }

    /**
     * Magic dynamic find/update/delete helpers:
     * - findOneBy{Column}($value)
     * - findAllBy{Column}($value)
     * - updateBy{Column}($value, array $data)
     * - deleteBy{Column}($value)
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (preg_match('/^(findOneBy|findAllBy|updateBy|deleteBy)([A-Z][A-Za-z0-9_]*)$/', $name, $m)) {
            $op = $m[1];
            $colPart = $m[2];
            $column = $this->normalizeColumn($colPart);

            switch ($op) {
                case 'findOneBy':
                    $value = $arguments[0] ?? null;
                    return $this->findOneBy([$column => $value]);
                case 'findAllBy':
                    $value = $arguments[0] ?? null;
                    return $this->findAllBy([$column => $value]);
                case 'updateBy':
                    $value = $arguments[0] ?? null;
                    $data = $arguments[1] ?? [];
                    if (!is_array($data)) {
                        throw new \InvalidArgumentException('updateBy* expects second argument as array $data');
                    }
                    return $this->updateBy([$column => $value], $data);
                case 'deleteBy':
                    $value = $arguments[0] ?? null;
                    return $this->deleteBy([$column => $value]);
            }
        }

        // Named scope call support: if a scope is registered with this method name, queue it and return $this
        if (isset($this->namedScopes[$name]) && is_callable($this->namedScopes[$name])) {
            $callable = $this->namedScopes[$name];
            // Bind arguments
            $this->runtimeScopes[] = function (&$criteria) use ($callable, $arguments) {
                $callable($criteria, ...$arguments);
            };
            return $this;
        }

        throw new \BadMethodCallException(static::class . "::{$name} does not exist");
    }

    protected function normalizeColumn(string $studly): string
    {
        // Convert StudlyCase/CamelCase to snake_case and lowercase
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $studly) ?? $studly;
        return strtolower($snake);
    }

    // ===== Scopes (MVP) =====

    /** Register a named scope callable: function(array &$criteria, ...$args): void */
    public function registerScope(string $name, callable $fn): static
    {
        $this->namedScopes[$name] = $fn;
        return $this;
    }

    /** Add an ad-hoc scope for the next query: callable(array &$criteria): void */
    public function scope(callable $fn): static
    {
        $this->runtimeScopes[] = $fn;
        return $this;
    }

    /** @param array<string,mixed> $criteria */
    private function applyRuntimeScopesToCriteria(array &$criteria): void
    {
        if (!$this->runtimeScopes) return;
        foreach ($this->runtimeScopes as $fn) {
            try { $fn($criteria); } catch (\Throwable) {}
        }
    }

    private function resetRuntimeScopes(): void
    {
        $this->runtimeScopes = [];
    }

    /**
     * Specify fields to select on the base entity and optionally on relations via dot-notation.
     * Example: fields('id', 'name', 'posts.title')
     */
    public function fields(string ...$fields): static
    {
        $base = [];
        foreach ($fields as $f) {
            if (str_contains($f, '.')) {
                [$rel, $col] = explode('.', $f, 2);
                if ($rel !== '') {
                    $this->relationFields[$rel][] = $col;
                }
            } else {
                if ($f !== '') { $base[] = $f; }
            }
        }
        if ($base) {
            $this->selectedFields = $base;
        } else {
            $this->selectedFields = $this->selectedFields ?? null;
        }
        return $this;
    }

    /** @param array<int, AbstractDto> $parents */
    protected function attachRelations(array $parents): void
    {
        if (!$parents) return;
        $relations = $this->relations();
        foreach ($this->with as $name) {
            if (!isset($relations[$name])) {
                continue; // silently ignore unknown
            }
            $config = $relations[$name];
            $type = (string)($config['type'] ?? '');
            $daoClass = $config['dao'] ?? null;
            $dtoClass = $config['dto'] ?? null; // kept for docs compatibility
            // Resolve related DAO: allow daoInstance or factory callable, else class-string
            $relatedDao = null;
            if (isset($config['daoInstance']) && is_object($config['daoInstance'])) {
                $relatedDao = $config['daoInstance'];
            } elseif (isset($config['factory']) && is_callable($config['factory'])) {
                try { $relatedDao = ($config['factory'])($this); } catch (\Throwable) { $relatedDao = null; }
            } elseif (is_string($daoClass)) {
                /** @var class-string<AbstractDao> $daoClass */
                try { $relatedDao = new $daoClass($this->getConnection()); } catch (\Throwable) { $relatedDao = null; }
            }
            if (!$relatedDao instanceof AbstractDao) { continue; }
            // Apply per-relation constraint, if any
            $constraint = $this->constraintForPath($name);
            if (is_callable($constraint)) {
                $constraint($relatedDao);
            }
            $relFields = $this->relationFields[$name] ?? null;
            if ($relFields) { $relatedDao->fields(...$relFields); }

            if ($type === 'hasMany' || $type === 'hasOne') {
                $foreignKey = (string)($config['foreignKey'] ?? '');
                $localKey = (string)($config['localKey'] ?? 'id');
                if ($foreignKey === '') continue;

                $keys = [];
                foreach ($parents as $p) {
                    $arr = $p->toArray();
                    if (isset($arr[$localKey])) { $keys[] = $arr[$localKey]; }
                }
                if (!$keys) continue;

                $children = $relatedDao->findAllWhereIn($foreignKey, $keys);
                // group children by foreignKey value
                $grouped = [];
                foreach ($children as $child) {
                    $fk = $child->toArray()[$foreignKey] ?? null;
                    if ($fk === null) continue;
                    $grouped[$fk][] = $child;
                }
                foreach ($parents as $p) {
                    $arr = $p->toArray();
                    $key = $arr[$localKey] ?? null;
                    $list = ($key !== null && isset($grouped[$key])) ? $grouped[$key] : [];
                    if ($type === 'hasOne') {
                        $first = $list[0] ?? null;
                        $p->setRelation($name, $first);
                    } else {
                        $p->setRelation($name, $list);
                    }
                }
                // Nested eager for children of this relation
                $nested = $this->withTree[$name] ?? [];
                if ($nested) {
                    // Flatten first-level child relation names for related DAO
                    $childNames = array_keys($nested);
                    // Prepare related DAO with child-level constraints (prefix path)
                    $relatedDao->with($this->rebuildNestedForChild($name, $nested));
                    // Collect all child DTOs (hasMany arrays concatenated; hasOne singletons filtered)
                    $allChildren = [];
                    foreach ($parents as $p) {
                        $val = $p->toArray(false)[$name] ?? null;
                        if ($val instanceof AbstractDto) {
                            $allChildren[] = $val;
                        } elseif (is_array($val)) {
                            foreach ($val as $c) { if ($c instanceof AbstractDto) { $allChildren[] = $c; } }
                        }
                    }
                    if ($allChildren) {
                        // Call attachRelations on the related DAO to process its with list
                        $relatedDao->attachRelations($allChildren);
                    }
                }
            } elseif ($type === 'belongsTo') {
                $foreignKey = (string)($config['foreignKey'] ?? ''); // on parent
                $otherKey = (string)($config['otherKey'] ?? 'id');    // on related
                if ($foreignKey === '') continue;

                $ownerIds = [];
                foreach ($parents as $p) {
                    $arr = $p->toArray();
                    if (isset($arr[$foreignKey])) { $ownerIds[] = $arr[$foreignKey]; }
                }
                if (!$ownerIds) continue;

                $owners = $relatedDao->findAllWhereIn($otherKey, $ownerIds);
                $byId = [];
                foreach ($owners as $o) {
                    $id = $o->toArray()[$otherKey] ?? null;
                    if ($id !== null) { $byId[$id] = $o; }
                }
                foreach ($parents as $p) {
                    $arr = $p->toArray();
                    $fk = $arr[$foreignKey] ?? null;
                    $p->setRelation($name, ($fk !== null && isset($byId[$fk])) ? $byId[$fk] : null);
                }
                // Nested eager for belongsTo owner
                $nested = $this->withTree[$name] ?? [];
                if ($nested) {
                    $childNames = array_keys($nested);
                    $relatedDao->with($this->rebuildNestedForChild($name, $nested));
                    $allOwners = [];
                    foreach ($parents as $p) {
                        $val = $p->toArray(false)[$name] ?? null;
                        if ($val instanceof AbstractDto) { $allOwners[] = $val; }
                    }
                    if ($allOwners) {
                        $relatedDao->attachRelations($allOwners);
                    }
                }
            } elseif ($type === 'belongsToMany') {
                // SQL-only many-to-many via pivot table
                $pivot = (string)($config['pivot'] ?? ($config['pivotTable'] ?? ''));
                $foreignPivotKey = (string)($config['foreignPivotKey'] ?? ''); // pivot column referencing parent
                $relatedPivotKey = (string)($config['relatedPivotKey'] ?? ''); // pivot column referencing related
                $localKey = (string)($config['localKey'] ?? 'id');            // on parent
                $relatedKey = (string)($config['relatedKey'] ?? 'id');        // on related
                if ($pivot === '' || $foreignPivotKey === '' || $relatedPivotKey === '') { continue; }

                // Collect parent keys
                $parentKeys = [];
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    if (isset($arr[$localKey])) { $parentKeys[] = $arr[$localKey]; }
                }
                if (!$parentKeys) continue;

                // Fetch pivot rows
                $ph = [];$bind=[];foreach (array_values(array_unique($parentKeys, SORT_REGULAR)) as $i=>$val){$k="p_$i";$ph[]=":$k";$bind[$k]=$val;}
                $pivotSql = 'SELECT ' . $foreignPivotKey . ' AS fk, ' . $relatedPivotKey . ' AS rk FROM ' . $pivot . ' WHERE ' . $foreignPivotKey . ' IN (' . implode(', ', $ph) . ')';
                $rows = $this->connection->query($pivotSql, $bind);
                if (!$rows) {
                    foreach ($parents as $p) { $p->setRelation($name, []); }
                    continue;
                }
                $byParent = [];
                $relatedIds = [];
                foreach ($rows as $r) {
                    $fkVal = $r['fk'] ?? null; $rkVal = $r['rk'] ?? null;
                    if ($fkVal === null || $rkVal === null) continue;
                    $byParent[(string)$fkVal][] = $rkVal;
                    $relatedIds[] = $rkVal;
                }
                if (!$relatedIds) {
                    foreach ($parents as $p) { $p->setRelation($name, []); }
                    continue;
                }
                $relatedIds = array_values(array_unique($relatedIds, SORT_REGULAR));
                // Apply constraints if provided
                $constraint = $this->constraintForPath($name);
                if (is_callable($constraint)) { $constraint($relatedDao); }
                $related = $relatedDao->findAllWhereIn($relatedKey, $relatedIds);
                $relatedMap = [];
                foreach ($related as $r) {
                    $id = $r->toArray(false)[$relatedKey] ?? null;
                    if ($id !== null) { $relatedMap[(string)$id] = $r; }
                }
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    $lk = $arr[$localKey] ?? null;
                    $ids = ($lk !== null && isset($byParent[(string)$lk])) ? $byParent[(string)$lk] : [];
                    $attached = [];
                    foreach ($ids as $rid) { if (isset($relatedMap[(string)$rid])) { $attached[] = $relatedMap[(string)$rid]; } }
                    $p->setRelation($name, $attached);
                }

                // Nested eager on related side
                $nested = $this->withTree[$name] ?? [];
                if ($nested && !empty($related)) {
                    $relatedDao->with($this->rebuildNestedForChild($name, $nested));
                    $relatedDao->attachRelations($related);
                }
            }
        }
        // reset eager-load request after use
        $this->with = [];
        $this->withTree = [];
        $this->withConstraints = [];
        $this->withStrategies = [];
        // do not reset relationFields here; they may be reused by subsequent loads in the same call
    }

    // ===== Join-based eager loading (opt-in, single-level) =====

    /** Determine if join-based eager should be used for current with() selection. */
    private function shouldUseJoinEager(): bool
    {
        // Determine if join strategy is desired globally or per relation
        $globalJoin = ($this->eagerStrategy === 'join');
        $perRelJoin = false;
        if (!$globalJoin && $this->with) {
            $allMarked = true;
            foreach ($this->with as $rel) {
                if (($this->withStrategies[$rel] ?? null) !== 'join') { $allMarked = false; break; }
            }
            $perRelJoin = $allMarked;
        }
        if (!$globalJoin && !$perRelJoin) return false;
        // Only single-level paths supported in join MVP (no nested trees)
        foreach ($this->withTree as $rel => $sub) {
            if (!empty($sub)) return false; // nested present => fallback
        }
        // Require relationFields for each relation to know what to select safely
        foreach ($this->with as $rel) {
            if (!isset($this->relationFields[$rel]) || empty($this->relationFields[$rel])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build a SELECT with LEFT JOINs for the requested relations.
     * Returns [sql, bindings, meta] where meta describes relation aliases and selected columns.
     * @param ?int $limit
     * @param ?int $offset
     * @return array{0:string,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function buildJoinSelect(string $baseWhere, array $bindings, ?int $limit, ?int $offset): array
    {
        $t0 = 't0';
        $pk = $this->getPrimaryKey();
        // Base select: ensure PK is included
        $baseCols = $this->selectedFields ?: ['*'];
        if ($baseCols === ['*'] || !in_array($pk, $baseCols, true)) {
            // Select * to keep behavior; PK is present implicitly
            $baseSelect = "$t0.*";
        } else {
            $quoted = array_map(fn($c) => "$t0.$c", $baseCols);
            $baseSelect = implode(', ', $quoted);
        }

        $selects = [ $baseSelect ];
        $joins = [];
        $meta = [ 'rels' => [] ];

        $relations = $this->relations();
        $aliasIndex = 1;
        foreach ($this->with as $name) {
            if (!isset($relations[$name])) continue;
            $cfg = $relations[$name];
            $type = (string)($cfg['type'] ?? '');
            $daoClass = $cfg['dao'] ?? null;
            if (!is_string($daoClass) || $type === '') continue;
            /** @var class-string<AbstractDao> $daoClass */
            $relDao = new $daoClass($this->getConnection());
            $ta = 't' . $aliasIndex++;
            $on = '';
            if ($type === 'hasMany' || $type === 'hasOne') {
                $foreignKey = (string)($cfg['foreignKey'] ?? '');
                $localKey = (string)($cfg['localKey'] ?? 'id');
                if ($foreignKey === '') continue;
                $on = "$ta.$foreignKey = $t0.$localKey";
            } elseif ($type === 'belongsTo') {
                $foreignKey = (string)($cfg['foreignKey'] ?? '');
                $otherKey = (string)($cfg['otherKey'] ?? 'id');
                if ($foreignKey === '') continue;
                $on = "$ta.$otherKey = $t0.$foreignKey";
            } else {
                // belongsToMany not supported in join MVP
                continue;
            }
            // Soft-delete scope for related in JOIN (append in ON)
            if ($relDao->hasSoftDeletes()) {
                $del = $relDao->softDeleteConfig()['deletedAt'] ?? 'deleted_at';
                $on .= " AND $ta.$del IS NULL";
            }
            $joins[] = 'LEFT JOIN ' . $relDao->getTable() . ' ' . $ta . ' ON ' . $on;
            // Select related fields with alias prefix
            $relCols = $this->relationFields[$name] ?? [];
            $pref = $name . '__';
            foreach ($relCols as $col) {
                $selects[] = "$ta.$col AS `{$pref}{$col}`";
            }
            $meta['rels'][$name] = [ 'alias' => $ta, 'type' => $type, 'dao' => $relDao, 'cols' => $relCols ];
        }

        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM ' . $this->getTable() . ' ' . $t0;
        if ($joins) {
            $sql .= ' ' . implode(' ', $joins);
        }
        if ($baseWhere !== '') {
            $sql .= ' WHERE ' . $baseWhere;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . (int)$offset;
        }
        return [$sql, $bindings, $meta];
    }

    /**
     * Hydrate DTOs from joined rows with aliased related columns.
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $meta
     * @return array<int,AbstractDto>
     */
    private function hydrateFromJoinRows(array $rows, array $meta): array
    {
        if (!$rows) return [];
        $pk = $this->getPrimaryKey();
        $out = [];
        $byId = [];
        foreach ($rows as $row) {
            // Split base and related segments (related segments are prefixed as rel__col)
            $base = [];
            $relSegments = [];
            foreach ($row as $k => $v) {
                if (is_string($k) && str_contains($k, '__')) {
                    [$rel, $col] = explode('__', $k, 2);
                    $relSegments[$rel][$col] = $v;
                } else {
                    $base[$k] = $v;
                }
            }
            $idVal = $base[$pk] ?? null;
            if ($idVal === null) {
                // cannot hydrate without PK; skip row
                continue;
            }
            $idKey = (string)$idVal;
            if (!isset($byId[$idKey])) {
                $dto = $this->hydrate($this->castRowFromStorage($base));
                $byId[$idKey] = $dto;
                $out[] = $dto;
            }
            $parent = $byId[$idKey];
            // Attach each relation if there are any non-null values
            foreach (($meta['rels'] ?? []) as $name => $info) {
                $seg = $relSegments[$name] ?? [];
                // Detect empty (all null)
                $allNull = true;
                foreach ($seg as $vv) { if ($vv !== null) { $allNull = false; break; } }
                if ($allNull) {
                    // Ensure default: hasMany => [], hasOne/belongsTo => null (only set if not already set)
                    if (!isset($parent->toArray(false)[$name])) {
                        if (($info['type'] ?? '') === 'hasMany') { $parent->setRelation($name, []); }
                        else { $parent->setRelation($name, null); }
                    }
                    continue;
                }
                /** @var AbstractDao $relDao */
                $relDao = $info['dao'];
                // Cast and hydrate child DTO
                $child = $relDao->hydrate($relDao->castRowFromStorage($seg));
                if (($info['type'] ?? '') === 'hasMany') {
                    $current = $parent->toArray(false)[$name] ?? [];
                    if (!is_array($current)) { $current = []; }
                    // Append; no dedup to keep simple
                    $current[] = $child;
                    $parent->setRelation($name, $current);
                } else {
                    $parent->setRelation($name, $child);
                }
            }
        }
        return $out;
    }

    // ===== belongsToMany helpers (pivot operations) =====

    /**
     * Attach related ids to a parent for a belongsToMany relation.
     * Returns number of rows inserted into the pivot table.
     * @param string $relationName
     * @param int|string $parentId
     * @param array<int,int|string> $relatedIds
     */
    public function attach(string $relationName, int|string $parentId, array $relatedIds): int
    {
        if (!$relatedIds) return 0;
        $cfg = $this->relations()[$relationName] ?? null;
        if (!is_array($cfg) || ($cfg['type'] ?? '') !== 'belongsToMany') {
            throw new \InvalidArgumentException("Relation '{$relationName}' is not a belongsToMany relation");
        }
        $pivot = (string)($cfg['pivot'] ?? ($cfg['pivotTable'] ?? ''));
        $fk = (string)($cfg['foreignPivotKey'] ?? '');
        $rk = (string)($cfg['relatedPivotKey'] ?? '');
        if ($pivot === '' || $fk === '' || $rk === '') {
            throw new \InvalidArgumentException("belongsToMany relation '{$relationName}' requires pivot, foreignPivotKey, relatedPivotKey");
        }
        $cols = [$fk, $rk];
        $valuesSql = [];
        $params = [];
        foreach (array_values(array_unique($relatedIds, SORT_REGULAR)) as $i => $rid) {
            $p1 = "p_fk_{$i}"; $p2 = "p_rk_{$i}";
            $valuesSql[] = '(:' . $p1 . ', :' . $p2 . ')';
            $params[$p1] = $parentId;
            $params[$p2] = $rid;
        }
        $sql = 'INSERT INTO ' . $pivot . ' (' . implode(', ', $cols) . ') VALUES ' . implode(', ', $valuesSql);
        return $this->connection->execute($sql, $params);
    }

    /**
     * Detach related ids from a parent for a belongsToMany relation. If $relatedIds is empty, detaches all.
     * Returns affected rows.
     * @param array<int,int|string> $relatedIds
     */
    public function detach(string $relationName, int|string $parentId, array $relatedIds = []): int
    {
        $cfg = $this->relations()[$relationName] ?? null;
        if (!is_array($cfg) || ($cfg['type'] ?? '') !== 'belongsToMany') {
            throw new \InvalidArgumentException("Relation '{$relationName}' is not a belongsToMany relation");
        }
        $pivot = (string)($cfg['pivot'] ?? ($cfg['pivotTable'] ?? ''));
        $fk = (string)($cfg['foreignPivotKey'] ?? '');
        $rk = (string)($cfg['relatedPivotKey'] ?? '');
        if ($pivot === '' || $fk === '' || $rk === '') {
            throw new \InvalidArgumentException("belongsToMany relation '{$relationName}' requires pivot, foreignPivotKey, relatedPivotKey");
        }
        $where = $fk . ' = :pid';
        $params = ['pid' => $parentId];
        if ($relatedIds) {
            $ph = [];
            foreach (array_values(array_unique($relatedIds, SORT_REGULAR)) as $i => $rid) { $k = "r_$i"; $ph[] = ":$k"; $params[$k] = $rid; }
            $where .= ' AND ' . $rk . ' IN (' . implode(', ', $ph) . ')';
        }
        $sql = 'DELETE FROM ' . $pivot . ' WHERE ' . $where;
        return $this->connection->execute($sql, $params);
    }

    /**
     * Sync the related ids set for a parent: attach missing, detach extra.
     * Returns ['attached' => int, 'detached' => int].
     * @param array<int,int|string> $relatedIds
     * @return array{attached:int,detached:int}
     */
    public function sync(string $relationName, int|string $parentId, array $relatedIds): array
    {
        $cfg = $this->relations()[$relationName] ?? null;
        if (!is_array($cfg) || ($cfg['type'] ?? '') !== 'belongsToMany') {
            throw new \InvalidArgumentException("Relation '{$relationName}' is not a belongsToMany relation");
        }
        $pivot = (string)($cfg['pivot'] ?? ($cfg['pivotTable'] ?? ''));
        $fk = (string)($cfg['foreignPivotKey'] ?? '');
        $rk = (string)($cfg['relatedPivotKey'] ?? '');
        if ($pivot === '' || $fk === '' || $rk === '') {
            throw new \InvalidArgumentException("belongsToMany relation '{$relationName}' requires pivot, foreignPivotKey, relatedPivotKey");
        }
        // Read current related ids
        $rows = $this->connection->query('SELECT ' . $rk . ' AS rk FROM ' . $pivot . ' WHERE ' . $fk . ' = :pid', ['pid' => $parentId]);
        $current = [];
        foreach ($rows as $r) { $v = $r['rk'] ?? null; if ($v !== null) { $current[(string)$v] = true; } }
        $target = [];
        foreach (array_values(array_unique($relatedIds, SORT_REGULAR)) as $v) { $target[(string)$v] = true; }
        $toAttach = array_diff_key($target, $current);
        $toDetach = array_diff_key($current, $target);
        $attached = $toAttach ? $this->attach($relationName, $parentId, array_keys($toAttach)) : 0;
        $detached = $toDetach ? $this->detach($relationName, $parentId, array_keys($toDetach)) : 0;
        return ['attached' => (int)$attached, 'detached' => (int)$detached];
    }

    public function with(array $relations): static
    {
        // Accept ['rel', 'rel.child'] or ['rel' => callable, 'rel.child' => callable]
        // Also accepts config arrays like ['rel' => ['strategy' => 'join']] and
        // ['rel' => ['strategy' => 'join', 'constraint' => callable]]
        $names = [];
        $tree = [];
        foreach ($relations as $key => $value) {
            if (is_int($key)) { // plain name
                $path = (string)$value;
                $this->insertRelationPath($tree, $path);
            } else { // constraint or config
                $path = (string)$key;
                if (is_array($value)) {
                    $strategy = isset($value['strategy']) ? strtolower((string)$value['strategy']) : null;
                    if ($strategy) { $this->withStrategies[$path] = $strategy; }
                    if (isset($value['constraint']) && is_callable($value['constraint'])) {
                        $this->withConstraints[$path] = $value['constraint'];
                    }
                } elseif (is_callable($value)) {
                    $this->withConstraints[$path] = $value;
                }
                $this->insertRelationPath($tree, $path);
            }
        }
        $this->withTree = $tree;
        $this->with = array_keys($tree); // first-level only
        return $this;
    }

    public function load(AbstractDto $dto, string $relation): void
    {
        $this->with([$relation]);
        $this->attachRelations([$dto]);
    }

    /** @param array<int, AbstractDto> $dtos */
    public function loadMany(array $dtos, string $relation): void
    {
        if (!$dtos) return;
        $this->with([$relation]);
        $this->attachRelations($dtos);
    }

    protected function hydrate(array $row): AbstractDto
    {
        $class = $this->dtoClass();
        /** @var AbstractDto $dto */
        $dto = $class::fromArray($row);
        $uow = UnitOfWork::current();
        if ($uow && !UnitOfWork::isSuspended()) {
            $pk = $this->getPrimaryKey();
            $idVal = $row[$pk] ?? null;
            if ($idVal !== null) {
                $uow->attach(static::class, (string)$idVal, $dto);
                // Bind this DAO to allow snapshot diffing to emit updates
                $uow->bindDao(static::class, (string)$idVal, $this);
            }
        }
        return $dto;
    }

    private function selectList(): string
    {
        if ($this->selectedFields && $this->selectedFields !== ['*']) {
            return implode(', ', $this->selectedFields);
        }
        // By default, select all columns when fields() is not used.
        return '*';
    }

    private function resetFieldSelections(): void
    {
        $this->selectedFields = null;
        $this->relationFields = [];
        $this->includeTrashed = false;
        $this->onlyTrashed = false;
    }

    // ===== with()/nested helpers =====

    private function insertRelationPath(array &$tree, string $path): void
    {
        $parts = array_values(array_filter(explode('.', $path), fn($p) => $p !== ''));
        if (!$parts) return;
        $level =& $tree;
        foreach ($parts as $i => $p) {
            if (!isset($level[$p])) { $level[$p] = []; }
            $level =& $level[$p];
        }
    }

    /** Build child-level with() array (flattened) for a nested subtree, preserving constraints under full paths. */
    private function rebuildNestedForChild(string $prefix, array $subtree): array
    {
        $out = [];
        foreach ($subtree as $name => $child) {
            $full = $prefix . '.' . $name;
            // include with constraint if exists
            if (isset($this->withConstraints[$full]) && is_callable($this->withConstraints[$full])) {
                $out[$name] = $this->withConstraints[$full];
            } else {
                $out[] = $name;
            }
        }
        return $out;
    }

    private function constraintForPath(string $path): mixed
    {
        return $this->withConstraints[$path] ?? null;
    }

    // ===== Schema helpers & behaviors =====

    protected function getSchema(): array
    {
        return $this->schema();
    }

    protected function hasSoftDeletes(): bool
    {
        $sd = $this->getSchema()['softDeletes'] ?? null;
        return is_array($sd) && !empty($sd['enabled']);
    }

    /** @return array{deletedAt?:string} */
    protected function softDeleteConfig(): array
    {
        $sd = $this->getSchema()['softDeletes'] ?? [];
        return is_array($sd) ? $sd : [];
    }

    /** @return array{createdAt?:string,updatedAt?:string} */
    protected function timestampsConfig(): array
    {
        $ts = $this->getSchema()['timestamps'] ?? [];
        return is_array($ts) ? $ts : [];
    }

    /** Returns array<string,string> cast map col=>type */
    protected function castsMap(): array
    {
        $cols = $this->getSchema()['columns'] ?? [];
        if (!is_array($cols)) return [];
        $map = [];
        foreach ($cols as $name => $meta) {
            if (is_array($meta) && isset($meta['cast']) && is_string($meta['cast'])) {
                $map[$name] = $meta['cast'];
            }
        }
        return $map;
    }

    // Note: default SELECT projection now always '*' unless fields() is used.

    /**
     * Apply default scopes (e.g., soft deletes) to criteria.
     * For now, we don't alter criteria array; soft delete is appended as SQL fragment.
     * This method allows future transformations.
     * @param array<string,mixed> $criteria
     * @return array<string,mixed>
     */
    protected function applyDefaultScopes(array $criteria): array
    {
        return $criteria;
    }

    /** Append soft-delete scope to a WHERE clause string (without bindings). */
    private function appendScopedWhere(string $where): string
    {
        if (!$this->hasSoftDeletes()) return $where;
        $deletedAt = $this->softDeleteConfig()['deletedAt'] ?? 'deleted_at';
        $frag = '';
        if ($this->onlyTrashed) {
            $frag = "{$deletedAt} IS NOT NULL";
        } elseif (!$this->includeTrashed) {
            $frag = "{$deletedAt} IS NULL";
        }
        if ($frag === '') return $where;
        if ($where === '' ) return $frag;
        return $where . ' AND ' . $frag;
    }

    /** Cast a database row to PHP types according to schema casts. */
    private function castRowFromStorage(array $row): array
    {
        $casts = $this->castsMap();
        if (!$casts) return $row;
        foreach ($casts as $col => $type) {
            if (!array_key_exists($col, $row)) continue;
            $row[$col] = $this->castFromStorage($type, $row[$col]);
        }
        return $row;
    }

    private function castFromStorage(string $type, mixed $value): mixed
    {
        if ($value === null) return null;
        // Support custom caster classes via class-string in schema 'cast'
        $caster = $this->resolveCaster($type);
        if ($caster) {
            return $caster->fromStorage($value);
        }
        switch ($type) {
            case 'int': return (int)$value;
            case 'float': return (float)$value;
            case 'bool': return (bool)$value;
            case 'string': return (string)$value;
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
                }
                return $value;
            case 'datetime':
                try {
                    return new \DateTimeImmutable(is_string($value) ? $value : (string)$value);
                } catch (\Throwable) {
                    return $value;
                }
            default:
                return $value;
        }
    }

    /** Prepare data for INSERT: filter known columns, auto timestamps, storage casting. */
    private function prepareForInsert(array $data): array
    {
        $data = $this->filterToKnownColumns($data);
        // timestamps
        $ts = $this->timestampsConfig();
        $now = $this->nowString();
        if (!empty($ts['createdAt']) && !array_key_exists($ts['createdAt'], $data)) {
            $data[$ts['createdAt']] = $now;
        }
        if (!empty($ts['updatedAt']) && !array_key_exists($ts['updatedAt'], $data)) {
            $data[$ts['updatedAt']] = $now;
        }
        return $this->castForStorageAll($data);
    }

    /** Prepare data for UPDATE: filter known columns, auto updatedAt, storage casting. */
    private function prepareForUpdate(array $data): array
    {
        $data = $this->filterToKnownColumns($data);
        $ts = $this->timestampsConfig();
        if (!empty($ts['updatedAt'])) {
            $data[$ts['updatedAt']] = $this->nowString();
        }
        return $this->castForStorageAll($data);
    }

    /** Keep only keys defined in schema columns (if any). */
    private function filterToKnownColumns(array $data): array
    {
        $cols = $this->getSchema()['columns'] ?? null;
        if (!is_array($cols) || !$cols) return $data;
        $allowed = array_fill_keys(array_keys($cols), true);
        return array_intersect_key($data, $allowed);
    }

    private function castForStorageAll(array $data): array
    {
        $casts = $this->castsMap();
        if (!$casts) return $data;
        foreach ($data as $k => $v) {
            if (isset($casts[$k])) {
                $data[$k] = $this->castForStorage($casts[$k], $v);
            }
        }
        return $data;
    }

    private function castForStorage(string $type, mixed $value): mixed
    {
        if ($value === null) return null;
        // Support custom caster classes via class-string in schema 'cast'
        $caster = $this->resolveCaster($type);
        if ($caster) {
            return $caster->toStorage($value);
        }
        switch ($type) {
            case 'int': return (int)$value;
            case 'float': return (float)$value;
            case 'bool': return (int)((bool)$value); // store as 0/1 for portability
            case 'string': return (string)$value;
            case 'json':
                if (is_string($value)) return $value;
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            case 'datetime':
                if ($value instanceof \DateTimeInterface) {
                    $utc = (new \DateTimeImmutable('@' . $value->getTimestamp()))->setTimezone(new \DateTimeZone('UTC'));
                    return $utc->format('Y-m-d H:i:s');
                }
                return (string)$value;
            default:
                return $value;
        }
    }

    /** Cache for resolved caster instances. @var array<string, CasterInterface> */
    private array $casterCache = [];

    /** Resolve a caster from a type/class string. */
    private function resolveCaster(string $type): ?CasterInterface
    {
        // Not a class-string? return null to use built-ins
        if (!class_exists($type)) {
            return null;
        }
        if (isset($this->casterCache[$type])) {
            return $this->casterCache[$type];
        }
        try {
            $obj = new $type();
        } catch (\Throwable) {
            return null;
        }
        if ($obj instanceof CasterInterface) {
            $this->casterCache[$type] = $obj;
            return $obj;
        }
        return null;
    }

    private function nowString(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ===== Optimistic locking (MVP) =====

    protected function hasOptimisticLocking(): bool
    {
        $lock = $this->getSchema()['locking'] ?? [];
        return is_array($lock) && isset($lock['type'], $lock['column']) && in_array($lock['type'], ['version','timestamp'], true);
    }

    /** @return array{type:string,column:string}|array{} */
    protected function lockingConfig(): array
    {
        $lock = $this->getSchema()['locking'] ?? [];
        return is_array($lock) ? $lock : [];
    }

    /** Execute an immediate update with optimistic locking when configured. */
    private function doImmediateUpdateWithLock(int|string $id, array $toStore): void
    {
        if (!$this->hasOptimisticLocking()) {
            // default path
            $sets = [];
            $params = [];
            foreach ($toStore as $col => $val) { $sets[] = "$col = :set_$col"; $params["set_$col"] = $val; }
            $params['pk'] = $id;
            $sql = 'UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $sets) . ' WHERE ' . $this->getPrimaryKey() . ' = :pk';
            $this->connection->execute($sql, $params);
            return;
        }

        $cfg = $this->lockingConfig();
        $col = (string)$cfg['column'];
        $type = (string)$cfg['type'];

        // Fetch current lock value
        $pk = $this->getPrimaryKey();
        $row = $this->connection->query('SELECT ' . $col . ' AS c FROM ' . $this->getTable() . ' WHERE ' . $pk . ' = :pk LIMIT 1', ['pk' => $id]);
        $current = $row[0]['c'] ?? null;

        // Build SETs
        $sets = [];
        $params = [];
        foreach ($toStore as $c => $v) { $sets[] = "$c = :set_$c"; $params["set_$c"] = $v; }

        if ($type === 'version') {
            // bump version
            $sets[] = $col . ' = ' . $col . ' + 1';
        }

        // WHERE with lock compare
        $params['pk'] = $id;
        $where = $pk . ' = :pk';
        if ($current !== null) {
            $params['exp_lock'] = $current;
            $where .= ' AND ' . $col . ' = :exp_lock';
        }

        $sql = 'UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $affected = $this->connection->execute($sql, $params);
        if ($current !== null && $affected === 0) {
            throw new \Pairity\Orm\OptimisticLockException('Optimistic lock failed for ' . static::class . ' id=' . (string)$id);
        }
    }

    // ===== Soft delete toggles =====

    public function withTrashed(): static
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = false;
        return $this;
    }

    public function onlyTrashed(): static
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = true;
        return $this;
    }

    // ===== Soft delete helpers & utilities =====

    /** Restore a soft-deleted row by primary key. No-op when soft deletes are disabled. */
    public function restoreById(int|string $id): int
    {
        if (!$this->hasSoftDeletes()) { return 0; }
        $deletedAt = $this->softDeleteConfig()['deletedAt'] ?? 'deleted_at';
        $sql = 'UPDATE ' . $this->getTable() . " SET {$deletedAt} = NULL WHERE " . $this->getPrimaryKey() . ' = :pk';
        return $this->connection->execute($sql, ['pk' => $id]);
    }

    /** Restore rows matching criteria. No-op when soft deletes are disabled. */
    public function restoreBy(array $criteria): int
    {
        if (!$this->hasSoftDeletes()) { return 0; }
        [$where, $bindings] = $this->buildWhere($criteria);
        if ($where === '') { return 0; }
        $deletedAt = $this->softDeleteConfig()['deletedAt'] ?? 'deleted_at';
        $sql = 'UPDATE ' . $this->getTable() . " SET {$deletedAt} = NULL WHERE " . $where;
        return $this->connection->execute($sql, $bindings);
    }

    /** Permanently delete a row by id even when soft deletes are enabled. */
    public function forceDeleteById(int|string $id): int
    {
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE ' . $this->getPrimaryKey() . ' = :pk';
        return $this->connection->execute($sql, ['pk' => $id]);
    }

    /** Permanently delete rows matching criteria even when soft deletes are enabled. */
    public function forceDeleteBy(array $criteria): int
    {
        [$where, $bindings] = $this->buildWhere($criteria);
        if ($where === '') { return 0; }
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE ' . $where;
        return $this->connection->execute($sql, $bindings);
    }

    /** Touch a row by updating only the configured updatedAt column, if timestamps are enabled. */
    public function touch(int|string $id): int
    {
        $ts = $this->timestampsConfig();
        if (empty($ts['updatedAt'])) { return 0; }
        $col = $ts['updatedAt'];
        $sql = 'UPDATE ' . $this->getTable() . " SET {$col} = :ts WHERE " . $this->getPrimaryKey() . ' = :pk';
        return $this->connection->execute($sql, ['ts' => $this->nowString(), 'pk' => $id]);
    }
}

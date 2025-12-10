<?php

namespace Pairity\Model;

use Pairity\Contracts\ConnectionInterface;
use Pairity\Contracts\DaoInterface;

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
    /** Soft delete include flags */
    private bool $includeTrashed = false;
    private bool $onlyTrashed = false;

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

    /** @param array<string,mixed> $criteria */
    public function findOneBy(array $criteria): ?AbstractDto
    {
        [$where, $bindings] = $this->buildWhere($this->applyDefaultScopes($criteria));
        $where = $this->appendScopedWhere($where);
        $sql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable() . ($where ? ' WHERE ' . $where : '') . ' LIMIT 1';
        $rows = $this->connection->query($sql, $bindings);
        $dto = isset($rows[0]) ? $this->hydrate($this->castRowFromStorage($rows[0])) : null;
        if ($dto && $this->with) {
            $this->attachRelations([$dto]);
        }
        $this->resetFieldSelections();
        return $dto;
    }

    public function findById(int|string $id): ?AbstractDto
    {
        return $this->findOneBy([$this->getPrimaryKey() => $id]);
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int, AbstractDto>
     */
    public function findAllBy(array $criteria = []): array
    {
        [$where, $bindings] = $this->buildWhere($this->applyDefaultScopes($criteria));
        $where = $this->appendScopedWhere($where);
        $sql = 'SELECT ' . $this->selectList() . ' FROM ' . $this->getTable() . ($where ? ' WHERE ' . $where : '');
        $rows = $this->connection->query($sql, $bindings);
        $dtos = array_map(fn($r) => $this->hydrate($this->castRowFromStorage($r)), $rows);
        if ($dtos && $this->with) {
            $this->attachRelations($dtos);
        }
        $this->resetFieldSelections();
        return $dtos;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): AbstractDto
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('insert() requires non-empty data');
        }
        $data = $this->prepareForInsert($data);
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ' . $this->getTable() . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->connection->execute($sql, $data);
        $id = $this->connection->lastInsertId();
        $pk = $this->getPrimaryKey();
        if ($id !== null) {
            return $this->findById($id) ?? $this->hydrate(array_merge($data, [$pk => $id]));
        }
        // Fallback when lastInsertId is unavailable: return hydrated DTO from provided data
        return $this->hydrate($this->castRowFromStorage($data));
    }

    /** @param array<string,mixed> $data */
    public function update(int|string $id, array $data): AbstractDto
    {
        if (empty($data)) {
            $existing = $this->findById($id);
            if ($existing) return $existing;
            throw new \InvalidArgumentException('No data provided to update and record not found');
        }
        $data = $this->prepareForUpdate($data);
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = :set_$col";
            $params["set_$col"] = $val;
        }
        $params['pk'] = $id;
        $sql = 'UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $sets) . ' WHERE ' . $this->getPrimaryKey() . ' = :pk';
        $this->connection->execute($sql, $params);
        $updated = $this->findById($id);
        if ($updated === null) {
            // As a fallback, hydrate using provided data + id
            $pk = $this->getPrimaryKey();
            return $this->hydrate($this->castRowFromStorage(array_merge($data, [$pk => $id])));
        }
        return $updated;
    }

    public function deleteById(int|string $id): int
    {
        if ($this->hasSoftDeletes()) {
            $columns = $this->softDeleteConfig();
            $deletedAt = $columns['deletedAt'] ?? 'deleted_at';
            $now = $this->nowString();
            $sql = 'UPDATE ' . $this->getTable() . " SET {$deletedAt} = :ts WHERE " . $this->getPrimaryKey() . ' = :pk';
            return $this->connection->execute($sql, ['ts' => $now, 'pk' => $id]);
        }
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE ' . $this->getPrimaryKey() . ' = :pk';
        return $this->connection->execute($sql, ['pk' => $id]);
    }

    /** @param array<string,mixed> $criteria */
    public function deleteBy(array $criteria): int
    {
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
        return $this->connection->execute($sql, $bindings);
    }

    /**
     * Update rows matching the given criteria with the provided data.
     *
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $data
     */
    public function updateBy(array $criteria, array $data): int
    {
        if (empty($data)) {
            return 0;
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
        return $this->connection->execute($sql, array_merge($setParams, $whereBindings));
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

        throw new \BadMethodCallException(static::class . "::{$name} does not exist");
    }

    protected function normalizeColumn(string $studly): string
    {
        // Convert StudlyCase/CamelCase to snake_case and lowercase
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $studly) ?? $studly;
        return strtolower($snake);
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
            if (!is_string($daoClass)) { continue; }

            /** @var class-string<AbstractDao> $daoClass */
            $relatedDao = new $daoClass($this->getConnection());
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
            }
        }
        // reset eager-load request after use
        $this->with = [];
        // do not reset relationFields here; they may be reused by subsequent loads in the same call
    }

    public function with(array $relations): static
    {
        $this->with = $relations;
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

    private function nowString(): string
    {
        return gmdate('Y-m-d H:i:s');
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

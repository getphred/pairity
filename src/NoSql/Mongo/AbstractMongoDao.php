<?php

namespace Pairity\NoSql\Mongo;

use Pairity\Model\AbstractDto;

/**
 * Base DAO for MongoDB collections returning DTOs.
 *
 * Usage: extend and implement collection() + dtoClass().
 */
abstract class AbstractMongoDao
{
    protected MongoConnectionInterface $connection;

    /** @var array<int,string>|null */
    private ?array $projection = null; // list of field names to include
    /** @var array<string,int> */
    private array $sortSpec = [];
    private ?int $limitVal = null;
    private ?int $skipVal = null;

    /** @var array<int,string> */
    private array $with = [];
    /** @var array<string, array<int,string>> */
    private array $relationFields = [];

    public function __construct(MongoConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /** Collection name (e.g., "users"). */
    abstract protected function collection(): string;

    /** @return class-string<AbstractDto> */
    abstract protected function dtoClass(): string;

    /** Access to underlying connection. */
    public function getConnection(): MongoConnectionInterface
    {
        return $this->connection;
    }

    /** Relation metadata (MVP). Override in concrete DAO. */
    protected function relations(): array
    {
        return [];
    }

    // ========= Query modifiers =========

    /**
     * Specify projection fields to include on base entity and optionally on relations via dot-notation.
     * Example: fields('email','name','posts.title')
     */
    public function fields(string ...$fields): static
    {
        $base = [];
        foreach ($fields as $f) {
            $f = (string)$f;
            if ($f === '') continue;
            if (str_contains($f, '.')) {
                [$rel, $col] = explode('.', $f, 2);
                if ($rel !== '' && $col !== '') {
                    $this->relationFields[$rel][] = $col;
                }
            } else {
                $base[] = $f;
            }
        }
        $this->projection = $base ?: null;
        return $this;
    }

    /** Sorting spec, e.g., sort(['created_at' => -1]) */
    public function sort(array $spec): static
    {
        // sanitize values to 1 or -1
        $out = [];
        foreach ($spec as $k => $v) {
            $out[(string)$k] = ((int)$v) < 0 ? -1 : 1;
        }
        $this->sortSpec = $out;
        return $this;
    }

    public function limit(int $n): static
    {
        $this->limitVal = max(0, $n);
        return $this;
    }

    public function skip(int $n): static
    {
        $this->skipVal = max(0, $n);
        return $this;
    }

    // ========= CRUD =========

    /** @param array<string,mixed>|Filter $filter */
    public function findOneBy(array|Filter $filter): ?AbstractDto
    {
        $opts = $this->buildOptions();
        $opts['limit'] = 1;
        $docs = $this->connection->find($this->databaseName(), $this->collection(), $this->normalizeFilterInput($filter), $opts);
        $this->resetModifiers();
        $row = $docs[0] ?? null;
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed>|Filter $filter
     * @param array<string,mixed> $options Additional options (merged after internal modifiers)
     * @return array<int,AbstractDto>
     */
    public function findAllBy(array|Filter $filter = [], array $options = []): array
    {
        $opts = $this->buildOptions();
        // external override/merge
        foreach ($options as $k => $v) { $opts[$k] = $v; }
        $docs = $this->connection->find($this->databaseName(), $this->collection(), $this->normalizeFilterInput($filter), $opts);
        $dtos = array_map(fn($d) => $this->hydrate($d), is_iterable($docs) ? $docs : []);
        if ($dtos && $this->with) {
            $this->attachRelations($dtos);
        }
        $this->resetModifiers();
        return $dtos;
    }

    public function findById(string $id): ?AbstractDto
    {
        return $this->findOneBy(['_id' => $id]);
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): AbstractDto
    {
        $id = $this->connection->insertOne($this->databaseName(), $this->collection(), $data);
        // fetch back
        return $this->findById($id) ?? $this->hydrate(array_merge($data, ['_id' => $id]));
    }

    /** @param array<string,mixed> $data */
    public function update(string $id, array $data): AbstractDto
    {
        $this->connection->updateOne($this->databaseName(), $this->collection(), ['_id' => $id], ['$set' => $data]);
        return $this->findById($id) ?? $this->hydrate(array_merge($data, ['_id' => $id]));
    }

    public function deleteById(string $id): int
    {
        return $this->connection->deleteOne($this->databaseName(), $this->collection(), ['_id' => $id]);
    }

    /** @param array<string,mixed>|Filter $filter */
    public function deleteBy(array|Filter $filter): int
    {
        // For MVP provide deleteOne semantic; bulk deletes could be added later
        return $this->connection->deleteOne($this->databaseName(), $this->collection(), $this->normalizeFilterInput($filter));
    }

    /** Upsert by id convenience. */
    public function upsertById(string $id, array $data): string
    {
        return $this->connection->upsertOne($this->databaseName(), $this->collection(), ['_id' => $id], ['$set' => $data]);
    }

    /** @param array<string,mixed>|Filter $filter @param array<string,mixed> $update */
    public function upsertBy(array|Filter $filter, array $update): string
    {
        return $this->connection->upsertOne($this->databaseName(), $this->collection(), $this->normalizeFilterInput($filter), $update);
    }

    /**
     * Fetch related docs where a field is within the given set of values.
     * @param string $field
     * @param array<int,string> $values
     * @return array<int,AbstractDto>
     */
    public function findAllWhereIn(string $field, array $values): array
    {
        if (!$values) return [];
        // Normalize values (unique)
        $values = array_values(array_unique($values));
        $opts = $this->buildOptions();
        $docs = $this->connection->find($this->databaseName(), $this->collection(), [ $field => ['$in' => $values] ], $opts);
        return array_map(fn($d) => $this->hydrate($d), is_iterable($docs) ? $docs : []);
    }

    // ========= Dynamic helpers =========

    public function __call(string $name, array $arguments): mixed
    {
        if (preg_match('/^(findOneBy|findAllBy|updateBy|deleteBy)([A-Z][A-Za-z0-9_]*)$/', $name, $m)) {
            $op = $m[1];
            $col = $this->normalizeColumn($m[2]);
            switch ($op) {
                case 'findOneBy':
                    return $this->findOneBy([$col => $arguments[0] ?? null]);
                case 'findAllBy':
                    return $this->findAllBy([$col => $arguments[0] ?? null]);
                case 'updateBy':
                    $value = $arguments[0] ?? null;
                    $data = $arguments[1] ?? [];
                    if (!is_array($data)) {
                        throw new \InvalidArgumentException('updateBy* expects second argument as array $data');
                    }
                    $one = $this->findOneBy([$col => $value]);
                    if (!$one) { return 0; }
                    $id = (string)($one->toArray(false)['_id'] ?? '');
                    $this->update($id, $data);
                    return 1;
                case 'deleteBy':
                    return $this->deleteBy([$col => $arguments[0] ?? null]);
            }
        }
        throw new \BadMethodCallException(static::class . "::{$name} does not exist");
    }

    // ========= Internals =========

    protected function normalizeColumn(string $studly): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $studly) ?? $studly;
        return strtolower($snake);
    }

    protected function hydrate(array $doc): AbstractDto
    {
        // Ensure _id is a string for DTO friendliness
        if (isset($doc['_id']) && !is_string($doc['_id'])) {
            $doc['_id'] = (string)$doc['_id'];
        }
        $class = $this->dtoClass();
        /** @var AbstractDto $dto */
        $dto = $class::fromArray($doc);
        return $dto;
    }

    /** @param array<string,mixed>|Filter $filter */
    private function normalizeFilterInput(array|Filter $filter): array
    {
        if ($filter instanceof Filter) {
            return $filter->toArray();
        }
        return $filter;
    }

    /** Build MongoDB driver options from current modifiers. */
    private function buildOptions(): array
    {
        $opts = [];
        if ($this->projection) {
            $proj = [];
            foreach ($this->projection as $f) { $proj[$f] = 1; }
            $opts['projection'] = $proj;
        }
        if ($this->sortSpec) { $opts['sort'] = $this->sortSpec; }
        if ($this->limitVal !== null) { $opts['limit'] = $this->limitVal; }
        if ($this->skipVal !== null) { $opts['skip'] = $this->skipVal; }
        return $opts;
    }

    private function resetModifiers(): void
    {
        $this->projection = null;
        $this->sortSpec = [];
        $this->limitVal = null;
        $this->skipVal = null;
        $this->with = [];
        $this->relationFields = [];
    }

    /** Resolve database name from collection string if provided as db.collection; else default to 'app'. */
    private function databaseName(): string
    {
        // Allow subclasses to define "db.collection" in collection() if they want to target a specific DB quickly
        $col = $this->collection();
        if (str_contains($col, '.')) {
            return explode('.', $col, 2)[0];
        }
        return 'app';
    }

    // ===== Relations (MVP) =====

    /** Eager load relations on next find* call. */
    public function with(array $relations): static
    {
        $this->with = $relations;
        return $this;
    }

    /** Lazy load a single relation for one DTO. */
    public function load(AbstractDto $dto, string $relation): void
    {
        $this->with([$relation]);
        $this->attachRelations([$dto]);
        // do not call resetModifiers here to avoid wiping user sort/limit; with() is cleared in attachRelations
    }

    /** @param array<int,AbstractDto> $dtos */
    public function loadMany(array $dtos, string $relation): void
    {
        if (!$dtos) return;
        $this->with([$relation]);
        $this->attachRelations($dtos);
    }

    /** @param array<int,AbstractDto> $parents */
    protected function attachRelations(array $parents): void
    {
        if (!$parents) return;
        $relations = $this->relations();
        foreach ($this->with as $name) {
            if (!isset($relations[$name])) continue;
            $cfg = $relations[$name];
            $type = (string)($cfg['type'] ?? '');
            $daoClass = $cfg['dao'] ?? null;
            if (!is_string($daoClass) || $type === '') continue;

            /** @var class-string<\Pairity\NoSql\Mongo\AbstractMongoDao> $daoClass */
            $related = new $daoClass($this->connection);
            $relFields = $this->relationFields[$name] ?? null;
            if ($relFields) { $related->fields(...$relFields); }

            if ($type === 'hasMany' || $type === 'hasOne') {
                $foreignKey = (string)($cfg['foreignKey'] ?? ''); // on child
                $localKey = (string)($cfg['localKey'] ?? '_id');   // on parent
                if ($foreignKey === '') continue;

                $keys = [];
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    if (isset($arr[$localKey])) { $keys[] = (string)$arr[$localKey]; }
                }
                if (!$keys) continue;

                $children = $related->findAllWhereIn($foreignKey, $keys);
                $grouped = [];
                foreach ($children as $child) {
                    $fk = $child->toArray(false)[$foreignKey] ?? null;
                    if ($fk !== null) { $grouped[(string)$fk][] = $child; }
                }
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    $key = isset($arr[$localKey]) ? (string)$arr[$localKey] : null;
                    $list = ($key !== null && isset($grouped[$key])) ? $grouped[$key] : [];
                    if ($type === 'hasOne') {
                        $p->setRelation($name, $list[0] ?? null);
                    } else {
                        $p->setRelation($name, $list);
                    }
                }
            } elseif ($type === 'belongsTo') {
                $foreignKey = (string)($cfg['foreignKey'] ?? ''); // on parent
                $otherKey = (string)($cfg['otherKey'] ?? '_id');   // on related
                if ($foreignKey === '') continue;

                $ownerIds = [];
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    if (isset($arr[$foreignKey])) { $ownerIds[] = (string)$arr[$foreignKey]; }
                }
                if (!$ownerIds) continue;

                $owners = $related->findAllWhereIn($otherKey, $ownerIds);
                $byId = [];
                foreach ($owners as $o) {
                    $id = $o->toArray(false)[$otherKey] ?? null;
                    if ($id !== null) { $byId[(string)$id] = $o; }
                }
                foreach ($parents as $p) {
                    $arr = $p->toArray(false);
                    $fk = isset($arr[$foreignKey]) ? (string)$arr[$foreignKey] : null;
                    $p->setRelation($name, ($fk !== null && isset($byId[$fk])) ? $byId[$fk] : null);
                }
            }
        }
        // reset eager-load request
        $this->with = [];
        // keep relationFields for potential subsequent relation loads within same high-level call
    }
}

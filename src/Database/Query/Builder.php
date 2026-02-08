<?php

declare(strict_types=1);

namespace Pairity\Database\Query;

use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Exceptions\DatabaseException;

/**
 * Class Builder
 *
 * Fluent query builder for constructing SQL queries.
 */
class Builder
{
    /**
     * @var string|Builder|null
     */
    public $from = null;

    /**
     * @var array|null
     */
    public ?array $columns = null;

    /**
     * @var bool
     */
    public bool $distinct = false;

    /**
     * @var array
     */
    public array $joins = [];

    /**
     * @var array
     */
    public array $wheres = [];

    /**
     * @var array
     */
    public array $groups = [];

    /**
     * @var array
     */
    public array $havings = [];

    /**
     * @var array
     */
    public array $orders = [];

    /**
     * @var int|null
     */
    public ?int $limit = null;

    /**
     * @var int|null
     */
    public ?int $offset = null;

    /**
     * @var bool|string|null
     */
    public bool|string|null $lock = null;

    /**
     * @var array|null
     */
    public ?array $aggregate = null;

    /**
     * @var string|null
     */
    protected ?string $dtoClass = null;

    /**
     * @var object|null
     */
    protected ?object $dao = null;

    /**
     * @var array
     */
    protected array $eagerLoad = [];

    /**
     * @var array
     */
    protected array $bindings = [
        'update' => [],
        'select' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    /**
     * @var array
     */
    public array $unions = [];

    /**
     * @var int|null
     */
    protected ?int $cacheSeconds = null;

    /**
     * @var bool
     */
    protected bool $withoutTenancy = false;

    /**
     * Builder constructor.
     *
     * @param \Pairity\Contracts\Database\DatabaseManagerInterface $db
     * @param ConnectionInterface $connection
     * @param Grammar $grammar
     */
    public function __construct(
        protected \Pairity\Contracts\Database\DatabaseManagerInterface $db,
        protected ConnectionInterface $connection,
        protected Grammar $grammar
    ) {
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param array|string $relations
     * @param string|\Closure|null $callback
     * @return $this
     */
    public function with(array|string $relations, string|\Closure|null $callback = null): self
    {
        if (is_string($relations)) {
            if ($callback instanceof \Closure) {
                $this->eagerLoad[$relations] = $callback;
            } elseif (is_string($callback)) {
                $this->eagerLoad[$relations] = function ($query) use ($callback) {
                    $query->select($callback);
                };
            } else {
                $this->eagerLoad[$relations] = function () {};
            }
        } else {
            foreach ($relations as $name => $constraints) {
                if (is_numeric($name)) {
                    $this->with($constraints);
                } else {
                    $this->with($name, $constraints);
                }
            }
        }

        return $this;
    }

    /**
     * Get the relationships that should be eager loaded.
     *
     * @return array
     */
    public function getEagerLoads(): array
    {
        return $this->eagerLoad;
    }

    /**
     * @return \Pairity\Contracts\Database\DatabaseManagerInterface
     */
    public function getDb(): \Pairity\Contracts\Database\DatabaseManagerInterface
    {
        return $this->db;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the query grammar instance.
     *
     * @return Grammar
     */
    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Set the columns to be selected.
     *
     * @param array|string|Expression $columns
     * @return $this
     */
    public function select(array|string|Expression $columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if ($columns === ['*']) {
            $this->columns = $columns;
            return $this;
        }

        foreach ($columns as $as => $column) {
            if ($column instanceof \Closure || $column instanceof Builder) {
                if (is_string($as)) {
                    $this->selectSubquery($column, $as);
                } else {
                    throw new DatabaseException('Select subqueries must be aliased.');
                }
            } elseif (is_string($as) && ($column instanceof Expression || is_string($column))) {
                $this->columns[] = [$column, $as];
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * Add a subquery to the select clause.
     *
     * @param \Closure|Builder $query
     * @param string $as
     * @return $this
     */
    protected function selectSubquery(\Closure|Builder $query, string $as): self
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        $this->columns[] = [$query, $as];

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param string|\Closure|Builder $table
     * @param string|null $as
     * @return $this
     */
    public function from(string|\Closure|Builder $table, ?string $as = null): self
    {
        if ($table instanceof \Closure) {
            return $this->fromSubquery($table, $as);
        }

        if ($table instanceof Builder) {
            return $this->fromSubquery($table, $as);
        }

        $this->from = $as ? "{$table} as {$as}" : $table;
        return $this;
    }

    /**
     * Set the table which the query is targeting as a subquery.
     *
     * @param \Closure|Builder $query
     * @param string|null $as
     * @return $this
     */
    protected function fromSubquery(\Closure|Builder $query, ?string $as = null): self
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        $this->from = [$query, $as ?: 'sub'];

        return $this;
    }

    /**
     * Create a new query builder instance.
     *
     * @return Builder
     */
    public function newQuery(): Builder
    {
        return new Builder($this->db, $this->connection, $this->grammar);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $type = 'Basic';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');
        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param string $column
     * @param string $boolean
     * @return $this
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "where exists" clause to the query.
     *
     * @param \Closure|Builder $callback
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereExists(\Closure|Builder $callback, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotExists' : 'Exists';

        if ($callback instanceof \Closure) {
            $query = $this->newQuery();
            $callback($query);
        } else {
            $query = $callback;
        }

        $this->wheres[] = compact('type', 'query', 'boolean');

        return $this;
    }

    /**
     * Add an "or where exists" clause to the query.
     *
     * @param \Closure|Builder $callback
     * @return $this
     */
    public function orWhereExists(\Closure|Builder $callback): self
    {
        return $this->whereExists($callback, 'or');
    }

    /**
     * Add a "where not exists" clause to the query.
     *
     * @param \Closure|Builder $callback
     * @param string $boolean
     * @return $this
     */
    public function whereNotExists(\Closure|Builder $callback, string $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Add an "or where not exists" clause to the query.
     *
     * @param \Closure|Builder $callback
     * @return $this
     */
    public function orWhereNotExists(\Closure|Builder $callback): self
    {
        return $this->whereNotExists($callback, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof \Closure || $values instanceof Builder) {
            return $this->whereInSubquery($column, $values, $boolean, $not);
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orWhereIn(string $column, mixed $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotIn(string $column, mixed $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orWhereNotIn(string $column, mixed $values): self
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a where in subquery clause to the query.
     *
     * @param string $column
     * @param \Closure|Builder $query
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    protected function whereInSubquery(string $column, \Closure|Builder $query, string $boolean, bool $not): self
    {
        $type = $not ? 'NotInSubquery' : 'InSubquery';

        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = compact('column', 'direction');
        return $this;
    }

    /**
     * Add a "limit" clause to the query.
     *
     * @param int $value
     * @return $this
     */
    public function limit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    /**
     * Add an "offset" clause to the query.
     *
     * @param int $value
     * @return $this
     */
    public function offset(int $value): self
    {
        $this->offset = $value;
        return $this;
    }

    /**
     * Set the "lock" value of the query.
     *
     * @param bool|string $value
     * @return $this
     */
    public function lock(bool|string $value = true): self
    {
        $this->lock = $value;
        return $this;
    }

    /**
     * Lock the selected rows in the database for updating.
     *
     * @return $this
     */
    public function lockForUpdate(): self
    {
        return $this->lock(true);
    }

    /**
     * Share lock the selected rows in the database.
     *
     * @return $this
     */
    public function sharedLock(): self
    {
        return $this->lock(false);
    }

    /**
     * Add a join clause to the query.
     *
     * @param string|\Closure|Builder $table
     * @param string|null|callable $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @return $this
     */
    public function join(string|\Closure|Builder $table, $first = null, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        if ($table instanceof \Closure || $table instanceof Builder) {
            return $this->joinSub($table, (string)$first, $operator, $second, $type);
        }

        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    /**
     * Add a join clause with a subquery to the query.
     *
     * @param \Closure|Builder $query
     * @param string $as
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return $this
     */
    public function joinSub(\Closure|Builder $query, string $as, $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        $this->joins[] = [
            'table' => [$query, $as],
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type,
        ];

        return $this;
    }

    /**
     * Add a left join clause to the query.
     *
     * @param string|\Closure|Builder $table
     * @param string|null|callable $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function leftJoin(string|\Closure|Builder $table, $first = null, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a left join clause with a subquery to the query.
     *
     * @param \Closure|Builder $query
     * @param string $as
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function leftJoinSub(\Closure|Builder $query, string $as, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    /**
     * Add a group by clause to the query.
     *
     * @param array|string $groups
     * @return $this
     */
    public function groupBy(array|string $groups): self
    {
        $groups = is_array($groups) ? $groups : func_get_args();
        $this->groups = array_merge($this->groups, $groups);
        return $this;
    }

    /**
     * Add a having clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $this->havings[] = compact('column', 'operator', 'value', 'boolean');
        $this->addBinding($value, 'having');
        return $this;
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql(): string
    {
        $this->applyTenancy();
        return $this->grammar->compileSelect($this);
    }

    /**
     * Get the SQL representation and bindings of the query.
     *
     * @return array{sql: string, bindings: array}
     */
    public function sql(): array
    {
        return [
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ];
    }

    /**
     * Set the query bindings.
     *
     * @param array $bindings
     * @param string $type
     * @return $this
     */
    public function setBindings(array $bindings, string $type = 'where'): self
    {
        if (!array_key_exists($type, $this->bindings)) {
            $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
            throw new \InvalidArgumentException($translator->trans('error.invalid_binding_type', ['type' => $type]));
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed $value
     * @param string $type
     * @return $this
     */
    public function addBinding(mixed $value, string $type = 'where'): self
    {
        if (!array_key_exists($type, $this->bindings)) {
            $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
            throw new \InvalidArgumentException($translator->trans('error.invalid_binding_type', ['type' => $type]));
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Get the current query bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings(): array
    {
        $flattened = [];

        // Add bindings from subquery in FROM
        if (is_array($this->from) && $this->from[0] instanceof Builder) {
            $flattened = array_merge($flattened, $this->from[0]->getBindings());
        }

        // Add bindings from other components
        foreach ($this->bindings as $type => $values) {
            if ($type === 'select') {
                $flattened = array_merge($flattened, $this->getSelectBindings());
            } elseif ($type === 'join') {
                $flattened = array_merge($flattened, $this->getJoinBindings());
            } elseif ($type === 'where') {
                $flattened = array_merge($flattened, $this->getWhereBindings());
            } elseif ($type === 'union') {
                // Unions already have their bindings added via addBinding in union()
                // But for nested queries, it's safer to flatten here if we were storing Builders
                $flattened = array_merge($flattened, $values);
            } else {
                $flattened = array_merge($flattened, $values);
            }
        }

        return $flattened;
    }

    /**
     * Get the bindings for the select clause, including subqueries.
     *
     * @return array
     */
    protected function getSelectBindings(): array
    {
        $bindings = [];

        foreach ((array) ($this->columns ?? []) as $column) {
            if (is_array($column) && $column[0] instanceof Builder) {
                $bindings = array_merge($bindings, $column[0]->getBindings());
            }
        }

        return $bindings;
    }

    /**
     * Get the bindings for the joins, including subqueries.
     *
     * @return array
     */
    protected function getJoinBindings(): array
    {
        $bindings = [];

        foreach ($this->joins as $join) {
            if (is_array($join['table']) && $join['table'][0] instanceof Builder) {
                $bindings = array_merge($bindings, $join['table'][0]->getBindings());
            }
        }

        return $bindings;
    }

    /**
     * Get the bindings for the where clauses, including subqueries.
     *
     * @return array
     */
    protected function getWhereBindings(): array
    {
        $bindings = [];

        foreach ($this->wheres as $where) {
            if (isset($where['query']) && $where['query'] instanceof Builder) {
                $bindings = array_merge($bindings, $where['query']->getBindings());
            } elseif (isset($where['values'])) {
                $bindings = array_merge($bindings, (array) $where['values']);
            } elseif (isset($where['value'])) {
                $bindings[] = $where['value'];
            }
        }

        return $bindings;
    }

    /**
     * Set the aggregate function and column for the query.
     *
     * @param string $function
     * @param array $columns
     * @return $this
     */
    public function aggregate(string $function, array $columns = ['*']): self
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->columns)) {
            $this->select($columns);
        }

        return $this;
    }

    /**
     * Handle dynamic calls to the query builder.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if ($this->dao && method_exists($this->dao, $scope = 'scope' . ucfirst($method))) {
            return $this->dao->$scope($this, ...$parameters) ?? $this;
        }

        $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
        throw new \BadMethodCallException($translator->trans('error.method_not_found', ['method' => $method]));
    }

    /**
     * Set the DTO class and DAO for the query.
     *
     * @param string $dtoClass
     * @param object $dao
     * @return $this
     */
    public function setModel(string $dtoClass, object $dao): self
    {
        $this->dtoClass = $dtoClass;
        $this->dao = $dao;
        return $this;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array|string $columns
     * @return object|null
     */
    public function first(array|string $columns = ['*']): ?object
    {
        $results = $this->limit(1)->get($columns);
        return $results[0] ?? null;
    }

    /**
     * Update or insert records.
     *
     * @param array $values
     * @param array $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        if (empty($values)) {
            return 0;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $bindings = $this->getUpsertBindings($values, $update);

        return $this->connection->execute(
            $this->grammar->compileUpsert($this, $values, $uniqueBy, $update),
            $bindings
        );
    }

    /**
     * Get the bindings for an upsert statement.
     *
     * @param array $values
     * @param array $update
     * @return array
     */
    protected function getUpsertBindings(array $values, array $update): array
    {
        $bindings = [];

        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        foreach ($update as $key => $value) {
            if (!is_numeric($key)) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    /**
     * Add a union statement to the query.
     *
     * @param Builder|\Closure $query
     * @param bool $all
     * @return $this
     */
    public function union(Builder|\Closure $query, bool $all = false): self
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        $this->unions[] = compact('query', 'all');

        $this->addBinding($query->getBindings(), 'union');

        return $this;
    }

    /**
     * Add a union all statement to the query.
     *
     * @param Builder|\Closure $query
     * @return $this
     */
    public function unionAll(Builder|\Closure $query): self
    {
        return $this->union($query, true);
    }

    /**
     * Indicate that the query results should be cached.
     *
     * @param int $seconds
     * @return $this
     */
    public function remember(int $seconds): self
    {
        $this->cacheSeconds = $seconds;
        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array|string $columns
     * @return array
     */
    public function get(array|string $columns = ['*']): array
    {
        $this->applyTenancy();

        if ($columns !== ['*']) {
            $this->select($columns);
        }

        if ($this->cacheSeconds !== null) {
            return $this->getCached($this->cacheSeconds);
        }

        return $this->executeSelect();
    }

    /**
     * Execute the select statement.
     *
     * @return array
     */
    protected function executeSelect(): array
    {
        $results = $this->connection->select($this->toSql(), $this->getBindings());

        if (!$this->dtoClass || !$this->dao) {
            return array_map(fn($row) => new QueryResult($row), $results);
        }

        $models = array_map(function ($row) {
            $primaryKey = $this->dao->getPrimaryKey();
            $id = $row[$primaryKey] ?? null;

            if ($id && $cached = $this->dao->getIdentityMap()->get($this->dtoClass, $id)) {
                return $cached;
            }

            $dto = $this->hydrate($row);

            if ($id) {
                $this->dao->getIdentityMap()->add($this->dtoClass, $id, $dto);
                $this->db->unitOfWork()->track($dto, \Pairity\Database\UnitOfWork::STATE_CLEAN);
            }

            return $dto;
        }, $results);

        if (count($models) > 0 && !empty($this->eagerLoad)) {
            $models = (new EagerLoader())->load($models, $this->eagerLoad);
        }

        return $models;
    }

    /**
     * Get the cached results of the query.
     *
     * @param int $seconds
     * @return array
     */
    protected function getCached(int $seconds): array
    {
        $cache = $this->db->getContainer()->get(\Psr\SimpleCache\CacheInterface::class);
        $key = 'query.' . md5($this->toSql() . serialize($this->getBindings()));

        if ($cache->has($key)) {
            return $cache->get($key);
        }

        $results = $this->executeSelect();
        $cache->set($key, $results, $seconds);

        return $results;
    }

    /**
     * Hydrate a single row into a DTO.
     *
     * @param array $row
     * @return object
     */
    protected function hydrate(array $row): object
    {
        $dto = new $this->dtoClass();

        if ($this->dao) {
            $dto->setDao($this->dao);
            if ($hydrator = $this->dao->getHydrator()) {
                $hydrator->hydrate($row, $dto);
            } else {
                // Fallback to constructor if no hydrator
                $dto = new $this->dtoClass($row);
                $dto->setDao($this->dao);
            }
        } else {
            // Fallback to constructor if no hydrator
            $dto = new $this->dtoClass($row);
        }

        return $dto;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $columns
     * @return int
     */
    public function count(string $columns = '*'): int
    {
        $backupModel = $this->dtoClass;
        $backupDao = $this->dao;
        $this->dtoClass = null;
        $this->dao = null;

        $results = $this->aggregate('count', [$columns])->get();

        $this->dtoClass = $backupModel;
        $this->dao = $backupDao;

        return (int) ($results[0]->aggregate ?? 0);
    }

    /**
     * Paginate the given query.
     *
     * @param int $perPage
     * @param int $currentPage
     * @return Paginator
     */
    public function paginate(int $perPage = 15, int $currentPage = 1): Paginator
    {
        $total = $this->count();

        // Use a new instance for the results to avoid conflicting with aggregate state
        $itemsBuilder = clone $this;
        $itemsBuilder->aggregate = null;

        $items = $itemsBuilder->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new Paginator($items, $total, $perPage, $currentPage);
    }

    /**
     * Update records in the database.
     *
     * @param array $values
     * @return int
     * @throws DatabaseException
     */
    public function update(array $values): int
    {
        $this->applyTenancy();

        $this->ensureConstrained('update');

        $this->addBinding(array_values($values), 'update');

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->execute($sql, $this->getBindings());
    }

    /**
     * Delete records from the database.
     *
     * @return int
     * @throws DatabaseException
     */
    public function delete(): int
    {
        $this->applyTenancy();

        $this->ensureConstrained('delete');

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->execute($sql, $this->getBindings());
    }

    /**
     * Disable multi-tenancy for the query.
     *
     * @return $this
     */
    public function withoutTenancy(): self
    {
        $this->withoutTenancy = true;
        return $this;
    }

    /**
     * Apply multi-tenancy scope to the query if enabled.
     *
     * @return void
     */
    protected function applyTenancy(): void
    {
        if ($this->withoutTenancy || !$this->dao) {
            return;
        }

        if ($this->dao->getOption('tenancy', false)) {
            (new \Pairity\Database\Query\Scopes\TenantScope())->apply($this);
        }
    }

    /**
     * Ensure the query is constrained if unconstrained queries are disabled.
     *
     * @param string $operation
     * @return void
     * @throws DatabaseException
     */
    protected function ensureConstrained(string $operation): void
    {
        $config = $this->connection->getConfig();
        $allowUnconstrained = $config['allow_unconstrained_queries'] ?? false;

        if (!$allowUnconstrained && empty($this->wheres)) {
            $translator = $this->db->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
            throw new DatabaseException(
                $translator->trans('error.unconstrained_query', ['operation' => $operation]),
                0,
                null,
                ['operation' => $operation]
            );
        }
    }
}

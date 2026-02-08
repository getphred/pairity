<?php

declare(strict_types=1);

namespace Pairity\Database\Query;

/**
 * Class Grammar
 *
 * Abstract base class for SQL grammars.
 */
abstract class Grammar
{
    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query): string
    {
        if ($query->columns === null) {
            $query->columns = ['*'];
        }

        $sql = trim($this->concatenate($this->compileComponents($query)));

        if (!empty($query->unions)) {
            $sql = $this->compileUnions($query, $sql);
        }

        return $sql;
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param Builder $query
     * @param array $values
     * @return string
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ?';
        }

        $columns = implode(', ', $columns);

        $wheres = $this->compileWheres($query, $query->wheres);

        return trim("update {$table} set {$columns} {$wheres}");
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param Builder $query
     * @return string
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        $wheres = $this->compileWheres($query, $query->wheres);

        return trim("delete from {$table} {$wheres}");
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param Builder $query
     * @return array
     */
    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if ($query->$component !== null) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile the "aggregate" part of a query.
     *
     * @param Builder $query
     * @param array $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, array $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);

        if ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    /**
     * Compile the "columns" part of a query.
     *
     * @param Builder $query
     * @param array $columns
     * @return string
     */
    protected function compileColumns(Builder $query, array $columns): string
    {
        if ($query->aggregate !== null) {
            return '';
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . implode(', ', array_map(function ($column) {
            if (is_array($column)) {
                [$query, $as] = $column;
                
                if ($query instanceof Builder) {
                    return '(' . $query->toSql() . ') as ' . $this->wrap($as);
                }

                return $this->wrap($query) . ' as ' . $this->wrap($as);
            }

            return $this->wrap($column);
        }, $columns));
    }

    /**
     * Compile the "from" part of a query.
     *
     * @param Builder $query
     * @param mixed $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table): string
    {
        if (is_array($table)) {
            [$subquery, $as] = $table;
            return 'from (' . $subquery->toSql() . ') as ' . $this->wrapTable($as);
        }

        return 'from ' . $this->wrapTable($table);
    }

    /**
     * Compile the "joins" part of a query.
     *
     * @param Builder $query
     * @param array $joins
     * @return string
     */
    protected function compileJoins(Builder $query, array $joins): string
    {
        return implode(' ', array_map(function ($join) {
            $table = $join['table'];

            if (is_array($table)) {
                [$subquery, $as] = $table;
                $table = '(' . $subquery->toSql() . ') as ' . $this->wrapTable($as);
            } else {
                $table = $this->wrapTable($table);
            }

            return "{$join['type']} join {$table} on {$this->wrap($join['first'])} {$join['operator']} {$this->wrap($join['second'])}";
        }, $joins));
    }

    /**
     * Compile the "wheres" part of a query.
     *
     * @param Builder $query
     * @param array $wheres
     * @return string
     */
    protected function compileWheres(Builder $query, array $wheres): string
    {
        if (empty($wheres)) {
            return '';
        }

        $sql = [];

        foreach ($wheres as $where) {
            $sql[] = "{$where['boolean']} " . $this->{"compileWhere{$where['type']}"}($query, $where);
        }

        return 'where ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * Compile a "where in" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereIn(Builder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        return "{$this->wrap($where['column'])} in ({$values})";
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereNotIn(Builder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '1 = 1';
        }

        $values = $this->parameterize($where['values']);

        return "{$this->wrap($where['column'])} not in ({$values})";
    }

    /**
     * Compile a "where in subquery" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereInSubquery(Builder $query, array $where): string
    {
        return "{$this->wrap($where['column'])} in ({$where['query']->toSql()})";
    }

    /**
     * Compile a "where not in subquery" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereNotInSubquery(Builder $query, array $where): string
    {
        return "{$this->wrap($where['column'])} not in ({$where['query']->toSql()})";
    }

    /**
     * Compile a "where exists" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereExists(Builder $query, array $where): string
    {
        return 'exists (' . $where['query']->toSql() . ')';
    }

    /**
     * Compile a "where not exists" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereNotExists(Builder $query, array $where): string
    {
        return 'not exists (' . $where['query']->toSql() . ')';
    }

    /**
     * Compile a basic where clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereBasic(Builder $query, array $where): string
    {
        return "{$this->wrap($where['column'])} {$where['operator']} ?";
    }

    /**
     * Compile a "where null" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereNull(Builder $query, array $where): string
    {
        return "{$this->wrap($where['column'])} is null";
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function compileWhereNotNull(Builder $query, array $where): string
    {
        return "{$this->wrap($where['column'])} is not null";
    }

    /**
     * Compile the "groups" part of a query.
     *
     * @param Builder $query
     * @param array $groups
     * @return string
     */
    protected function compileGroups(Builder $query, array $groups): string
    {
        if (empty($groups)) {
            return '';
        }

        return 'group by ' . $this->columnize($groups);
    }

    /**
     * Compile the "havings" part of a query.
     *
     * @param Builder $query
     * @param array $havings
     * @return string
     */
    protected function compileHavings(Builder $query, array $havings): string
    {
        if (empty($havings)) {
            return '';
        }

        return 'having ' . $this->removeLeadingBoolean(implode(' ', array_map(function ($having) {
            return "{$having['boolean']} {$this->wrap($having['column'])} {$having['operator']} ?";
        }, $havings)));
    }

    /**
     * Compile the "orders" part of a query.
     *
     * @param Builder $query
     * @param array $orders
     * @return string
     */
    protected function compileOrders(Builder $query, array $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        return 'order by ' . implode(', ', array_map(function ($order) {
            return "{$this->wrap($order['column'])} {$order['direction']}";
        }, $orders));
    }

    /**
     * Compile the "limit" part of a query.
     *
     * @param Builder $query
     * @param int $limit
     * @return string
     */
    protected function compileLimit(Builder $query, int $limit): string
    {
        return 'limit ' . (int) $limit;
    }

    /**
     * Compile the "offset" part of a query.
     *
     * @param Builder $query
     * @param int $offset
     * @return string
     */
    protected function compileOffset(Builder $query, int $offset): string
    {
        return 'offset ' . (int) $offset;
    }

    /**
     * Compile an upsert statement into SQL.
     *
     * @param Builder $query
     * @param array $values
     * @param array $uniqueBy
     * @param array|null $update
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, ?array $update = null): string
    {
        $translator = $query->getDb()->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
        throw new \Pairity\Exceptions\DatabaseException($translator->trans('error.upsert_not_supported'));
    }

    /**
     * Compile the "lock" part of a query.
     *
     * @param Builder $query
     * @param bool|string $lock
     * @return string
     */
    protected function compileLock(Builder $query, bool|string $lock): string
    {
        if (is_string($lock)) {
            return $lock;
        }

        return $lock ? 'for update' : 'lock in share mode';
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @param array $values
     * @return string
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param mixed $value
     * @return string
     */
    public function parameter(mixed $value): string
    {
        return '?';
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param string $table
     * @return string
     */
    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param mixed $value
     * @return string
     */
    public function wrap(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if ($value === '*') {
            return $value;
        }

        return '"' . str_replace('"', '""', (string)$value) . '"';
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param array $columns
     * @return string
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param array $segments
     * @return string
     */
    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Compile the "union" queries adjoined to the main query.
     *
     * @param Builder $query
     * @param string $sql
     * @return string
     */
    protected function compileUnions(Builder $query, string $sql): string
    {
        $unionSql = '';

        foreach ($query->unions as $union) {
            $unionSql .= $this->compileUnion($union);
        }

        return '(' . $sql . ')' . $unionSql;
    }

    /**
     * Compile a single union statement.
     *
     * @param array $union
     * @return string
     */
    protected function compileUnion(array $union): string
    {
        $joiner = $union['all'] ? ' union all ' : ' union ';

        return $joiner . '(' . $union['query']->toSql() . ')';
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param string $value
     * @return string
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }
}

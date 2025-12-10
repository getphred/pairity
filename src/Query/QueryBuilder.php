<?php

namespace Pairity\Query;

use Pairity\Contracts\QueryBuilderInterface;

class QueryBuilder implements QueryBuilderInterface
{
    private array $columns = ['*'];
    private string $from = '';
    private ?string $alias = null;
    private array $joins = [];
    private array $wheres = [];
    private array $groupBys = [];
    private array $havings = [];
    private array $orderBys = [];
    private ?int $limitVal = null;
    private ?int $offsetVal = null;
    /** @var array<string,mixed> */
    private array $bindings = [];

    public function select(array $columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function from(string $table, ?string $alias = null): static
    {
        $this->from = $table;
        $this->alias = $alias;
        return $this;
    }

    public function join(string $type, string $table, string $on): static
    {
        $this->joins[] = trim(strtoupper($type)) . " JOIN {$table} ON {$on}";
        return $this;
    }

    public function where(string $clause, array $bindings = []): static
    {
        $this->wheres[] = $clause;
        foreach ($bindings as $k => $v) {
            $this->bindings[$k] = $v;
        }
        return $this;
    }

    public function orderBy(string $orderBy): static
    {
        $this->orderBys[] = $orderBy;
        return $this;
    }

    public function groupBy(string $groupBy): static
    {
        $this->groupBys[] = $groupBy;
        return $this;
    }

    public function having(string $clause, array $bindings = []): static
    {
        $this->havings[] = $clause;
        foreach ($bindings as $k => $v) {
            $this->bindings[$k] = $v;
        }
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitVal = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        if ($this->from) {
            $sql .= ' FROM ' . $this->from;
            if ($this->alias) {
                $sql .= ' ' . $this->alias;
            }
        }
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }
        if ($this->havings) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }
        if ($this->orderBys) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }
        if ($this->limitVal !== null) {
            $sql .= ' LIMIT ' . $this->limitVal;
        }
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ' . $this->offsetVal;
        }
        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}

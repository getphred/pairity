<?php

namespace Pairity\NoSql\Mongo;

/**
 * Minimal fluent builder for MongoDB filters.
 */
final class Filter
{
    /** @var array<string,mixed> */
    private array $query = [];

    private function __construct(array $initial = [])
    {
        $this->query = $initial;
    }

    public static function make(): self
    {
        return new self();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->query;
    }

    public function whereEq(string $field, mixed $value): self
    {
        $this->query[$field] = $value;
        return $this;
    }

    /** @param array<int,mixed> $values */
    public function whereIn(string $field, array $values): self
    {
        $this->query[$field] = ['$in' => array_values($values)];
        return $this;
    }

    public function gt(string $field, mixed $value): self
    {
        $this->op($field, '$gt', $value);
        return $this;
    }

    public function gte(string $field, mixed $value): self
    {
        $this->op($field, '$gte', $value);
        return $this;
    }

    public function lt(string $field, mixed $value): self
    {
        $this->op($field, '$lt', $value);
        return $this;
    }

    public function lte(string $field, mixed $value): self
    {
        $this->op($field, '$lte', $value);
        return $this;
    }

    /** Add an $or clause with an array of filters (arrays or Filter instances). */
    public function orWhere(array $conditions): self
    {
        $ors = [];
        foreach ($conditions as $c) {
            if ($c instanceof self) {
                $ors[] = $c->toArray();
            } elseif (is_array($c)) {
                $ors[] = $c;
            }
        }
        if (!empty($ors)) {
            $this->query['$or'] = $ors;
        }
        return $this;
    }

    private function op(string $field, string $op, mixed $value): void
    {
        $cur = $this->query[$field] ?? [];
        if (!is_array($cur)) { $cur = []; }
        $cur[$op] = $value;
        $this->query[$field] = $cur;
    }
}

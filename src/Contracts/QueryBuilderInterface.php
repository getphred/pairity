<?php

namespace Pairity\Contracts;

interface QueryBuilderInterface
{
    public function select(array $columns): static;
    public function from(string $table, ?string $alias = null): static;
    public function join(string $type, string $table, string $on): static;
    public function where(string $clause, array $bindings = []): static;
    public function orderBy(string $orderBy): static;
    public function groupBy(string $groupBy): static;
    public function having(string $clause, array $bindings = []): static;
    public function limit(int $limit): static;
    public function offset(int $offset): static;
    public function toSql(): string;
    /** @return array<string, mixed> */
    public function getBindings(): array;
}

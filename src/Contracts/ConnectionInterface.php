<?php

namespace Pairity\Contracts;

interface ConnectionInterface
{
    /**
     * Execute a SELECT (or any returning) statement and fetch all rows as associative arrays.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array;

    /**
     * Execute a non-SELECT statement (INSERT/UPDATE/DELETE).
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return int affected rows
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Run a callback within a transaction.
     * Rolls back on throwable and rethrows it.
     *
     * @template T
     * @param callable($this):T $callback
     * @return mixed
     */
    public function transaction(callable $callback): mixed;

    /**
     * Return the underlying driver connection (e.g., PDO).
     * @return mixed
     */
    public function getNative(): mixed;

    /**
     * Get last inserted ID if supported.
     */
    public function lastInsertId(): ?string;
}

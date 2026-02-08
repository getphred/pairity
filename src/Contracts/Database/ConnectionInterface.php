<?php

declare(strict_types=1);

namespace Pairity\Contracts\Database;

use PDO;
use PDOStatement;

/**
 * Interface ConnectionInterface
 *
 * Defines the contract for a database connection.
 *
 * @package Pairity\Contracts\Database
 */
interface ConnectionInterface
{
    /**
     * Get the underlying PDO instance for read operations.
     *
     * @return PDO
     */
    public function getReadPdo(): PDO;

    /**
     * Get the underlying PDO instance for write operations.
     *
     * @return PDO
     */
    public function getWritePdo(): PDO;

    /**
     * Execute a SQL statement and return the number of affected rows.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function execute(string $query, array $bindings = []): int;

    /**
     * Execute a SELECT query and return the results.
     *
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function select(string $query, array $bindings = []): array;

    /**
     * Run a raw SQL query.
     *
     * @param string $query
     * @param array $bindings
     * @return PDOStatement
     */
    public function query(string $query, array $bindings = []): PDOStatement;

    /**
     * Check if the connection is healthy.
     *
     * @return bool
     */
    public function checkHealth(): bool;

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit(): void;

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollBack(): void;

    /**
     * Add an interceptor to the connection.
     *
     * @param InterceptorInterface $interceptor
     * @return void
     */
    public function addInterceptor(InterceptorInterface $interceptor): void;

    /**
     * Get the current transaction level.
     *
     * @return int
     */
    public function transactionLevel(): int;
    /**
     * Get the driver instance.
     *
     * @return DriverInterface
     */
    public function getDriver(): DriverInterface;

    /**
     * Get the connection configuration.
     *
     * @return array
     */
    public function getConfig(): array;
}

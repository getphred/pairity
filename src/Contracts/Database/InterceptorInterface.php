<?php

declare(strict_types=1);

namespace Pairity\Contracts\Database;

/**
 * Interface InterceptorInterface
 *
 * Defines the contract for a database query interceptor (middleware).
 *
 * @package Pairity\Contracts\Database
 */
interface InterceptorInterface
{
    /**
     * Intercept a database operation.
     *
     * @param string $query The SQL query.
     * @param array $bindings The query bindings.
     * @param string $mode The connection mode (read/write).
     * @param callable $next The next interceptor or the final query execution.
     * @return mixed
     */
    public function intercept(string $query, array $bindings, string $mode, callable $next): mixed;
}

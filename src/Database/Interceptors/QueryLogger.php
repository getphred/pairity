<?php

declare(strict_types=1);

namespace Pairity\Database\Interceptors;

use Pairity\Contracts\Database\InterceptorInterface;

/**
 * Class QueryLogger
 *
 * Interceptor for logging queries and their execution time.
 */
class QueryLogger implements InterceptorInterface
{
    /**
     * @var array<array{sql: string, bindings: array, time: float, mode: string}>
     */
    protected array $logs = [];

    /**
     * @inheritDoc
     */
    public function intercept(string $query, array $bindings, string $mode, callable $next): mixed
    {
        $start = microtime(true);

        try {
            return $next($query, $bindings, $mode);
        } finally {
            $time = (microtime(true) - $start) * 1000; // ms
            $this->logs[] = compact('query', 'bindings', 'time', 'mode');
        }
    }

    /**
     * Get all logged queries.
     *
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Clear the query logs.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->logs = [];
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Exceptions;

/**
 * Class QueryException
 *
 * Exception thrown when a database query fails.
 *
 * @package Pairity\Exceptions
 */
class QueryException extends DatabaseException
{
    /**
     * QueryException constructor.
     *
     * @param string $sql The SQL query that failed.
     * @param array<mixed> $bindings The query bindings.
     * @param string $message The error message.
     * @param int $code The error code.
     * @param \Throwable|null $previous The previous exception.
     */
    public function __construct(
        protected string $sql,
        protected array $bindings,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, [
            'sql' => $sql,
            'bindings' => $bindings,
        ]);
    }

    /**
     * Get the SQL query that failed.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the query bindings.
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}

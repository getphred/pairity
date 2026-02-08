<?php

declare(strict_types=1);

namespace Pairity\Exceptions;

use Exception;
use Throwable;

/**
 * Class PairityException
 *
 * Base exception class for the Pairity ORM.
 * All internal exceptions should extend this class to allow for consistent catching.
 *
 * @package Pairity\Exceptions
 */
class PairityException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        protected array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

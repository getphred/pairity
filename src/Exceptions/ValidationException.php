<?php

declare(strict_types=1);

namespace Pairity\Exceptions;

/**
 * Class ValidationException
 *
 * Exception thrown when data validation fails.
 *
 * @package Pairity\Exceptions
 */
class ValidationException extends PairityException
{
    /**
     * ValidationException constructor.
     *
     * @param array<string, array<string>> $errors The validation errors.
     * @param string $message The error message.
     * @param int $code The error code.
     * @param \Throwable|null $previous The previous exception.
     */
    public function __construct(
        protected array $errors,
        string $message = "Validation failed.",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, ['errors' => $errors]);
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

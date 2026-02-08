<?php

declare(strict_types=1);

namespace Pairity\Contracts\Console;

/**
 * Interface CommandInterface
 *
 * Defines the contract for all CLI commands in the Pairity tool.
 * Every command must implement this interface to be registered and executed
 * by the application console.
 *
 * @package Pairity\Contracts\Console
 */
interface CommandInterface
{
    /**
     * Get the name of the command (e.g., 'migrate', 'make:model').
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the description of what the command does.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Execute the command logic.
     *
     * @param array $args Command line arguments.
     * @param array $options Command line options (flags).
     *
     * @return int The exit code (0 for success, non-zero for error).
     */
    public function execute(array $args, array $options): int;
}

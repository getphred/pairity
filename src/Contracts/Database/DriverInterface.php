<?php

declare(strict_types=1);

namespace Pairity\Contracts\Database;

use PDO;

/**
 * Interface DriverInterface
 *
 * Defines the contract for a database driver.
 *
 * @package Pairity\Contracts\Database
 */
interface DriverInterface
{
    /**
     * Create a new PDO instance based on the configuration.
     *
     * @param array $config
     * @return PDO
     */
    public function connect(array $config): PDO;

    /**
     * Get the driver name (e.g., 'mysql', 'sqlite').
     *
     * @return string
     */
    public function getName(): string;
}

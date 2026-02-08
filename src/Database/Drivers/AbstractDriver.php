<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

use Pairity\Contracts\Database\DriverInterface;
use PDO;

/**
 * Class AbstractDriver
 *
 * Base class for database drivers.
 *
 * @package Pairity\Database\Drivers
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * Default PDO options.
     *
     * @var array
     */
    protected array $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * @inheritDoc
     */
    public function connect(array $config): PDO
    {
        $dsn = $this->buildDsn($config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = array_replace($this->options, $config['options'] ?? []);

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Build the DSN string for the driver.
     *
     * @param array $config
     * @return string
     */
    abstract protected function buildDsn(array $config): string;
}

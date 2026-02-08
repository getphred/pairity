<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

/**
 * Class SQLiteDriver
 *
 * Driver for SQLite databases.
 *
 * @package Pairity\Database\Drivers
 */
class SQLiteDriver extends AbstractDriver
{
    /**
     * @inheritDoc
     */
    protected function buildDsn(array $config): string
    {
        return "sqlite:" . ($config['database'] ?? ':memory:');
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'sqlite';
    }
}

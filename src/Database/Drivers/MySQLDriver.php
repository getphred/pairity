<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

/**
 * Class MySQLDriver
 *
 * Driver for MySQL databases.
 *
 * @package Pairity\Database\Drivers
 */
class MySQLDriver extends AbstractDriver
{
    /**
     * @inheritDoc
     */
    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database}";

        if ($charset) {
            $dsn .= ";charset={$charset}";
        }

        return $dsn;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'mysql';
    }
}

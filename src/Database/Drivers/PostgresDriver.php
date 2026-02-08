<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

/**
 * Class PostgresDriver
 *
 * Driver for PostgreSQL databases.
 *
 * @package Pairity\Database\Drivers
 */
class PostgresDriver extends AbstractDriver
{
    /**
     * @inheritDoc
     */
    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? '';
        $schema = $config['schema'] ?? 'public';
        $sslMode = $config['sslmode'] ?? null;

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};options='--search_path={$schema}'";

        if ($sslMode) {
            $dsn .= ";sslmode={$sslMode}";
        }

        return $dsn;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'pgsql';
    }
}

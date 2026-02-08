<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

/**
 * Class SqlServerDriver
 *
 * Driver for SQL Server databases.
 *
 * @package Pairity\Database\Drivers
 */
class SqlServerDriver extends AbstractDriver
{
    /**
     * @inheritDoc
     */
    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 1433;
        $database = $config['database'] ?? '';
        $appName = $config['appname'] ?? 'Pairity';

        $dsn = "sqlsrv:Server={$host},{$port};Database={$database};APP={$appName}";

        if (isset($config['encrypt'])) {
            $dsn .= ";Encrypt=" . ($config['encrypt'] ? 'true' : 'false');
        }

        if (isset($config['trust_server_certificate'])) {
            $dsn .= ";TrustServerCertificate=" . ($config['trust_server_certificate'] ? 'true' : 'false');
        }

        return $dsn;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'sqlsrv';
    }
}

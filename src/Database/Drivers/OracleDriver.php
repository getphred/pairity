<?php

declare(strict_types=1);

namespace Pairity\Database\Drivers;

/**
 * Class OracleDriver
 *
 * Driver for Oracle databases.
 *
 * @package Pairity\Database\Drivers
 */
class OracleDriver extends AbstractDriver
{
    /**
     * @inheritDoc
     */
    protected function buildDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 1521;
        $database = $config['database'] ?? '';
        $serviceName = $config['service_name'] ?? $database;
        $charset = $config['charset'] ?? 'AL32UTF8';

        // Oracle DSN using Easy Connect syntax
        $dsn = "oci:dbname=//{$host}:{$port}/{$serviceName}";

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
        return 'oracle';
    }
}

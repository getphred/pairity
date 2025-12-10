<?php

namespace Pairity\Database;

use PDO;
use Pairity\Contracts\ConnectionInterface;

final class ConnectionManager
{
    /**
     * @param array<string,mixed> $config
     */
    public static function make(array $config): ConnectionInterface
    {
        $driver = strtolower((string)($config['driver'] ?? ''));
        if ($driver === '') {
            throw new \InvalidArgumentException('Database config must include a driver');
        }

        [$dsn, $username, $password, $options] = self::buildDsn($driver, $config);
        $pdo = new PDO($dsn, $username, $password, $options);
        return new PdoConnection($pdo);
    }

    /**
     * @param array<string,mixed> $config
     * @return array{0:string,1:?string,2:?string,3:array<string,mixed>}
     */
    private static function buildDsn(string $driver, array $config): array
    {
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $config['options'] ?? [];

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $host = $config['host'] ?? '127.0.0.1';
                $port = (int)($config['port'] ?? 3306);
                $db = $config['database'] ?? '';
                $charset = $config['charset'] ?? 'utf8mb4';
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                return [$dsn, $username, $password, $options];

            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $host = $config['host'] ?? '127.0.0.1';
                $port = (int)($config['port'] ?? 5432);
                $db = $config['database'] ?? '';
                $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                return [$dsn, $username, $password, $options];

            case 'sqlite':
                $path = $config['path'] ?? ($config['database'] ?? ':memory:');
                $dsn = str_starts_with($path, 'memory') || $path === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $path;
                // For SQLite, username/password are typically null
                return [$dsn, null, null, $options];

            case 'sqlsrv':
            case 'mssql':
                $host = $config['host'] ?? '127.0.0.1';
                $port = (int)($config['port'] ?? 1433);
                $db = $config['database'] ?? '';
                $server = $port ? "$host,$port" : $host;
                $dsn = "sqlsrv:Server={$server};Database={$db}";
                if (!isset($options[PDO::SQLSRV_ATTR_ENCODING])) {
                    $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
                }
                return [$dsn, $username, $password, $options];

            case 'oci':
            case 'oracle':
                $host = $config['host'] ?? '127.0.0.1';
                $port = (int)($config['port'] ?? 1521);
                $service = $config['service_name'] ?? ($config['sid'] ?? 'XE');
                $charset = $config['charset'] ?? 'AL32UTF8';
                $dsn = "oci:dbname=//{$host}:{$port}/{$service};charset={$charset}";
                return [$dsn, $username, $password, $options];

            default:
                throw new \InvalidArgumentException("Unsupported driver: {$driver}");
        }
    }
}

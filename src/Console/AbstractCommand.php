<?php

namespace Pairity\Console;

use Pairity\Database\ConnectionManager;
use Pairity\Migrations\Migrator;
use Pairity\Migrations\MigrationLoader;

abstract class AbstractCommand implements CommandInterface
{
    protected function stdout(string $msg): void
    {
        echo $msg . PHP_EOL;
    }

    protected function stderr(string $msg): void
    {
        fwrite(STDERR, $msg . PHP_EOL);
    }

    protected function getConnection(array $args): \Pairity\Contracts\ConnectionInterface
    {
        $config = $this->loadConfig($args);
        return ConnectionManager::make($config);
    }

    protected function loadConfig(array $args): array
    {
        if (isset($args['config'])) {
            $path = (string)$args['config'];
            if (!is_file($path)) {
                throw new \InvalidArgumentException("Config file not found: {$path}");
            }
            $cfg = require $path;
            if (!is_array($cfg)) {
                throw new \InvalidArgumentException('Config file must return an array');
            }
            return $cfg;
        }

        $driver = getenv('DB_DRIVER') ?: null;
        if ($driver) {
            $driver = strtolower($driver);
            $cfg = ['driver' => $driver];
            switch ($driver) {
                case 'mysql':
                case 'mariadb':
                    $cfg += [
                        'host' => getenv('DB_HOST') ?: '127.0.0.1',
                        'port' => (int)(getenv('DB_PORT') ?: 3306),
                        'database' => getenv('DB_DATABASE') ?: '',
                        'username' => getenv('DB_USERNAME') ?: null,
                        'password' => getenv('DB_PASSWORD') ?: null,
                        'charset'  => getenv('DB_CHARSET') ?: 'utf8mb4',
                    ];
                    break;
                case 'pgsql':
                case 'postgres':
                case 'postgresql':
                    $cfg += [
                        'host' => getenv('DB_HOST') ?: '127.0.0.1',
                        'port' => (int)(getenv('DB_PORT') ?: 5432),
                        'database' => getenv('DB_DATABASE') ?: '',
                        'username' => getenv('DB_USERNAME') ?: null,
                        'password' => getenv('DB_PASSWORD') ?: null,
                    ];
                    break;
                case 'sqlite':
                    $cfg += [
                        'path' => getenv('DB_PATH') ?: 'db.sqlite',
                    ];
                    break;
                case 'sqlsrv':
                case 'mssql':
                    $cfg += [
                        'host' => getenv('DB_HOST') ?: '127.0.0.1',
                        'port' => (int)(getenv('DB_PORT') ?: 1433),
                        'database' => getenv('DB_DATABASE') ?: '',
                        'username' => getenv('DB_USERNAME') ?: null,
                        'password' => getenv('DB_PASSWORD') ?: null,
                    ];
                    break;
            }
            return $cfg;
        }

        // Fallback to SQLite in project root
        return [
            'driver' => 'sqlite',
            'path' => 'db.sqlite'
        ];
    }

    protected function getMigrationsDir(array $args): string
    {
        $dir = $args['path'] ?? 'migrations';
        if (!str_starts_with($dir, '/') && !str_starts_with($dir, './')) {
            $dir = getcwd() . DIRECTORY_SEPARATOR . $dir;
        }
        return $dir;
    }
}

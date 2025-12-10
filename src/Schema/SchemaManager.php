<?php

namespace Pairity\Schema;

use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\Grammars\Grammar;
use Pairity\Schema\Grammars\MySqlGrammar;
use Pairity\Schema\Grammars\SqliteGrammar;
use Pairity\Schema\Grammars\PostgresGrammar;
use Pairity\Schema\Grammars\SqlServerGrammar;
use Pairity\Schema\Grammars\OracleGrammar;
use PDO;

final class SchemaManager
{
    public static function forConnection(ConnectionInterface $connection): Builder
    {
        $grammar = self::detectGrammar($connection);
        return new Builder($connection, $grammar);
    }

    private static function detectGrammar(ConnectionInterface $connection): Grammar
    {
        $native = $connection->getNative();
        $driver = null;
        if ($native instanceof PDO) {
            try {
                $driver = $native->getAttribute(PDO::ATTR_DRIVER_NAME);
            } catch (\Throwable) {
                $driver = null;
            }
        }
        $driver = is_string($driver) ? strtolower($driver) : '';
        return match ($driver) {
            'sqlite' => new SqliteGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlsrv' => new SqlServerGrammar(),
            'oci' => new OracleGrammar(),
            'oracle' => new OracleGrammar(),
            default => new MySqlGrammar(), // default to MySQL-style grammar
        };
    }
}

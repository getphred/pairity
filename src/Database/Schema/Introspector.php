<?php

declare(strict_types=1);

namespace Pairity\Database\Schema;

use Pairity\Contracts\Database\ConnectionInterface;

/**
 * Class Introspector
 *
 * Reverse-engineers database schema into Pairity YAML format.
 */
class Introspector
{
    /**
     * Introspector constructor.
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(
        protected ConnectionInterface $connection
    ) {
    }

    /**
     * Get all tables from the database.
     *
     * @return array<string>
     */
    public function getTables(): array
    {
        $driver = $this->connection->getDriver()->getName();
        
        $sql = match ($driver) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            'mysql' => "SHOW TABLES",
            'pgsql', 'postgres' => "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'",
            default => throw new \RuntimeException("Introspection not supported for driver [{$driver}]."),
        };

        $results = $this->connection->select($sql);
        
        return array_map(fn($row) => (array)$row === $row ? reset($row) : $row->{key((array)$row)}, $results);
    }

    /**
     * Reverse-engineer a table into a YAML-compatible array.
     *
     * @param string $table
     * @return array<string, mixed>
     */
    public function introspectTable(string $table): array
    {
        $driver = $this->connection->getDriver()->getName();
        
        return match ($driver) {
            'sqlite' => $this->introspectSqliteTable($table),
            'mysql' => $this->introspectMysqlTable($table),
            default => throw new \RuntimeException("Introspection for table not supported for driver [{$driver}]."),
        };
    }

    /**
     * Introspect a SQLite table.
     *
     * @param string $table
     * @return array<string, mixed>
     */
    protected function introspectSqliteTable(string $table): array
    {
        $columns = $this->connection->select("PRAGMA table_info({$table})");
        $yaml = ['columns' => []];

        foreach ($columns as $column) {
            $name = $column->name;
            $type = strtolower($column->type);
            $definition = [
                'type' => $this->mapType($type),
            ];

            if ($column->notnull) $definition['nullable'] = false;
            else $definition['nullable'] = true;

            if ($column->dflt_value !== null) $definition['default'] = $column->dflt_value;
            if ($column->pk) $definition['primary'] = true;

            $yaml['columns'][$name] = $definition;
        }

        return $yaml;
    }

    /**
     * Introspect a MySQL table.
     *
     * @param string $table
     * @return array<string, mixed>
     */
    protected function introspectMysqlTable(string $table): array
    {
        $columns = $this->connection->select("SHOW COLUMNS FROM {$table}");
        $yaml = ['columns' => []];

        foreach ($columns as $column) {
            $name = $column->Field;
            $type = strtolower($column->Type);
            $definition = [
                'type' => $this->mapType($type),
            ];

            if ($column->Null === 'NO') $definition['nullable'] = false;
            if ($column->Default !== null) $definition['default'] = $column->Default;
            if ($column->Key === 'PRI') $definition['primary'] = true;
            if (str_contains($column->Extra, 'auto_increment')) $definition['autoIncrement'] = true;

            $yaml['columns'][$name] = $definition;
        }

        return $yaml;
    }

    /**
     * Map database types to Pairity types.
     *
     * @param string $dbType
     * @return string
     */
    protected function mapType(string $dbType): string
    {
        if (str_contains($dbType, 'int')) return 'integer';
        if (str_contains($dbType, 'char') || str_contains($dbType, 'text')) return 'string';
        if (str_contains($dbType, 'bool')) return 'boolean';
        if (str_contains($dbType, 'float') || str_contains($dbType, 'double') || str_contains($dbType, 'decimal')) return 'decimal';
        if (str_contains($dbType, 'date') || str_contains($dbType, 'time')) return 'datetime';
        
        return 'string'; // Default fallback
    }
}

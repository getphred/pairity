<?php

namespace Pairity\Schema;

use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\Grammars\SqliteGrammar;

/**
 * Best-effort table rebuild strategy for SQLite to emulate unsupported ALTER operations
 * (drop column, rename column) across older SQLite versions.
 *
 * Limitations: complex constraints, triggers, foreign keys, and advanced index options are not preserved.
 * Indexes declared in the provided Blueprint (indexes/uniques/dropIndex/dropUnique) will be applied after rebuild.
 */
final class SqliteTableRebuilder
{
    public static function rebuild(ConnectionInterface $connection, Blueprint $blueprint, SqliteGrammar $grammar): void
    {
        $table = $blueprint->table;

        // Read existing columns
        $columns = $connection->query('PRAGMA table_info(' . self::wrapIdent($table) . ')');
        if (!$columns) {
            throw new \RuntimeException('Table not found for rebuild: ' . $table);
        }

        // Build rename map
        $renameMap = [];
        foreach ($blueprint->renameColumns as $pair) {
            $renameMap[$pair['from']] = $pair['to'];
        }

        $dropSet = array_flip($blueprint->dropColumns);

        // Build new column definitions from existing columns (apply drop/rename)
        $newCols = [];
        $sourceToTarget = [];
        foreach ($columns as $col) {
            $name = (string)$col['name'];
            if (isset($dropSet[$name])) continue; // drop
            $targetName = $renameMap[$name] ?? $name;
            $type = (string)($col['type'] ?? 'TEXT');
            $notnull = ((int)($col['notnull'] ?? 0)) === 1 ? 'NOT NULL' : 'NULL';
            $default = $col['dflt_value'] ?? null; // already SQL literal in PRAGMA output
            $pk = ((int)($col['pk'] ?? 0)) === 1 ? 'PRIMARY KEY' : '';

            $defParts = [self::wrap($targetName), $type, $notnull];
            if ($default !== null && $default !== '') {
                $defParts[] = 'DEFAULT ' . $default;
            }
            if ($pk !== '') {
                $defParts[] = $pk;
            }
            $newCols[$targetName] = implode(' ', array_filter($defParts));
            $sourceToTarget[$name] = $targetName;
        }

        // Add newly declared columns from Blueprint (with their definitions via grammar)
        foreach ($blueprint->columns as $def) {
            $newCols[$def->name] = self::compileColumnSqlite($def, $grammar);
        }

        // Temp table name
        $tmp = $table . '_rebuild_' . substr(sha1((string)microtime(true)), 0, 6);

        // Create temp table
        $create = 'CREATE TABLE ' . self::wrap($tmp) . ' (' . implode(', ', array_values($newCols)) . ')';
        $connection->execute($create);

        // Build INSERT INTO tmp (...) SELECT ... FROM table
        $targetCols = array_keys($newCols);
        $selectExprs = [];
        foreach ($targetCols as $colName) {
            // If this column existed before, map from old source name (pre-rename), else insert NULL
            $sourceName = array_search($colName, $sourceToTarget, true);
            if ($sourceName !== false) {
                $selectExprs[] = self::wrap($sourceName) . ' AS ' . self::wrap($colName);
            } else {
                $selectExprs[] = 'NULL AS ' . self::wrap($colName);
            }
        }
        $insert = 'INSERT INTO ' . self::wrap($tmp) . ' (' . self::columnList($targetCols) . ') SELECT ' . implode(', ', $selectExprs) . ' FROM ' . self::wrap($table);
        $connection->execute($insert);

        // Replace original table
        $connection->execute('DROP TABLE ' . self::wrap($table));
        $connection->execute('ALTER TABLE ' . self::wrap($tmp) . ' RENAME TO ' . self::wrap($table));

        // Apply index/unique operations from the blueprint (post-rebuild)
        $post = new Blueprint($table);
        // Carry over index ops only
        $post->uniques = $blueprint->uniques;
        $post->indexes = $blueprint->indexes;
        $post->dropUniqueNames = $blueprint->dropUniqueNames;
        $post->dropIndexNames = $blueprint->dropIndexNames;

        $sqls = $grammar->compileAlter($post);
        foreach ($sqls as $sql) {
            $connection->execute($sql);
        }
    }

    private static function compileColumnSqlite(ColumnDefinition $c, SqliteGrammar $grammar): string
    {
        // Minimal re-use: instantiate a throwaway Blueprint to access protected compile via public path is not possible; duplicate minimal mapping here
        $type = match ($c->type) {
            'increments', 'bigincrements', 'integer', 'biginteger' => 'INTEGER',
            'string' => 'VARCHAR(' . ($c->length ?? 255) . ')',
            'text' => 'TEXT',
            'boolean' => 'INTEGER',
            'json' => 'TEXT',
            'datetime' => 'TEXT',
            'decimal' => 'NUMERIC',
            default => strtoupper($c->type),
        };
        $parts = [self::wrap($c->name), $type];
        $parts[] = $c->nullable ? 'NULL' : 'NOT NULL';
        if ($c->default !== null) {
            $parts[] = 'DEFAULT ' . self::quoteDefault($c->default);
        }
        return implode(' ', $parts);
    }

    private static function wrap(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    private static function wrapIdent(string $ident): string
    {
        // For PRAGMA table_info(<name>) we should not quote with double quotes; wrap in simple name if needed
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    private static function columnList(array $cols): string
    {
        return implode(', ', array_map(fn($c) => self::wrap($c), $cols));
    }

    private static function quoteDefault(mixed $value): string
    {
        if (is_numeric($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

<?php

namespace Pairity\Schema\Grammars;

use Pairity\Schema\Blueprint;
use Pairity\Schema\ColumnDefinition;

class SqliteGrammar extends Grammar
{
    public function compileCreate(Blueprint $blueprint): array
    {
        $cols = [];
        foreach ($blueprint->columns as $col) {
            $cols[] = $this->compileColumn($col, $blueprint);
        }

        $inline = [];
        if ($blueprint->primary) {
            // In SQLite, INTEGER PRIMARY KEY on a single column should be on the column itself for autoincrement.
            // For composite PKs, use table constraint.
            if (count($blueprint->primary) > 1) {
                $inline[] = 'PRIMARY KEY (' . $this->columnList($blueprint->primary) . ')';
            }
        }
        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? null;
            $inline[] = 'UNIQUE' . ($name ? ' ' . $this->wrap($name) : '') . ' (' . $this->columnList($u['columns']) . ')';
        }

        $definition = implode(",\n  ", array_merge($cols, $inline));
        $sql = 'CREATE TABLE ' . $this->wrap($blueprint->table) . " (\n  {$definition}\n)";

        $statements = [$sql];
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? ($blueprint->table . '_' . implode('_', $i['columns']) . '_index');
            $statements[] = 'CREATE INDEX ' . $this->wrap($name) . ' ON ' . $this->wrap($blueprint->table) . ' (' . $this->columnList($i['columns']) . ')';
        }

        return $statements;
    }

    public function compileDrop(string $table): array
    {
        return ['DROP TABLE ' . $this->wrap($table)];
    }

    public function compileDropIfExists(string $table): array
    {
        return ['DROP TABLE IF EXISTS ' . $this->wrap($table)];
    }

    public function compileAlter(\Pairity\Schema\Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->table);
        $stmts = [];

        // SQLite supports ADD COLUMN straightforwardly
        foreach ($blueprint->columns as $col) {
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->compileColumn($col, $blueprint);
        }

        // RENAME COLUMN and DROP COLUMN are supported in modern SQLite (3.25+ and 3.35+). We will emit statements; if not supported by the runtime, DB will error.
        foreach ($blueprint->renameColumns as $pair) {
            $stmts[] = 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $this->wrap($pair['from']) . ' TO ' . $this->wrap($pair['to']);
        }
        foreach ($blueprint->dropColumns as $name) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $this->wrap($name);
        }

        // Unique/index operations in SQLite generally require CREATE/DROP INDEX statements
        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? ($blueprint->table . '_' . implode('_', $u['columns']) . '_unique');
            $stmts[] = 'CREATE UNIQUE INDEX ' . $this->wrap($name) . ' ON ' . $table . ' (' . $this->columnList($u['columns']) . ')';
        }
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? ($blueprint->table . '_' . implode('_', $i['columns']) . '_index');
            $stmts[] = 'CREATE INDEX ' . $this->wrap($name) . ' ON ' . $table . ' (' . $this->columnList($i['columns']) . ')';
        }
        foreach ($blueprint->dropUniqueNames as $n) {
            $stmts[] = 'DROP INDEX IF EXISTS ' . $this->wrap($n);
        }
        foreach ($blueprint->dropIndexNames as $n) {
            $stmts[] = 'DROP INDEX IF EXISTS ' . $this->wrap($n);
        }

        // Rename table
        if ($blueprint->renameTo) {
            $stmts[] = 'ALTER TABLE ' . $table . ' RENAME TO ' . $this->wrap($blueprint->renameTo);
        }

        return $stmts ?: ['-- no-op'];
    }

    private function compileColumn(ColumnDefinition $c, Blueprint $bp): string
    {
        // SQLite type affinities
        $type = match ($c->type) {
            'increments' => 'INTEGER',
            'bigincrements' => 'INTEGER',
            'integer' => 'INTEGER',
            'biginteger' => 'INTEGER',
            'string' => 'VARCHAR(' . ($c->length ?? 255) . ')',
            'text' => 'TEXT',
            'boolean' => 'INTEGER',
            'json' => 'TEXT',
            'datetime' => 'TEXT',
            'decimal' => 'NUMERIC',
            default => strtoupper($c->type),
        };

        $parts = [$this->wrap($c->name), $type];

        // AUTOINCREMENT style: only valid for a single-column integer primary key
        $isPk = (count($bp->primary) === 1 && $bp->primary[0] === $c->name) || ($c->autoIncrement === true);
        if ($isPk && in_array($c->type, ['increments','bigincrements','integer','biginteger'], true)) {
            $parts[] = 'PRIMARY KEY';
            if ($c->autoIncrement) {
                $parts[] = 'AUTOINCREMENT';
            }
        }

        $parts[] = $c->nullable ? 'NULL' : 'NOT NULL';

        if ($c->default !== null) {
            $parts[] = 'DEFAULT ' . $this->quoteDefault($c->default);
        }

        return implode(' ', $parts);
    }

    private function columnList(array $cols): string
    {
        return implode(', ', array_map(fn($c) => $this->wrap($c), $cols));
    }

    protected function wrap(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_numeric($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

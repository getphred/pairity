<?php

namespace Pairity\Schema\Grammars;

use Pairity\Schema\Blueprint;
use Pairity\Schema\ColumnDefinition;

class SqlServerGrammar extends Grammar
{
    public function compileCreate(BLueprint $blueprint): array
    {
        $cols = [];
        foreach ($blueprint->columns as $col) {
            $cols[] = $this->compileColumn($col);
        }

        $inline = [];
        if ($blueprint->primary) {
            $inline[] = 'PRIMARY KEY (' . $this->columnList($blueprint->primary) . ')';
        }
        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? null;
            $inline[] = 'CONSTRAINT ' . ($name ? $this->wrap($name) : $this->wrap($blueprint->table . '_' . implode('_', $u['columns']) . '_unique')) . ' UNIQUE (' . $this->columnList($u['columns']) . ')';
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
        return ['IF OBJECT_ID(N' . $this->quote($table) . ", 'U') IS NOT NULL DROP TABLE " . $this->wrap($table)];
    }

    public function compileAlter(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->table);
        $stmts = [];

        foreach ($blueprint->columns as $col) {
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD ' . $this->compileColumn($col);
        }

        foreach ($blueprint->dropColumns as $name) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $this->wrap($name);
        }

        foreach ($blueprint->renameColumns as $pair) {
            $stmts[] = 'EXEC sp_rename ' . $this->quote($blueprint->table . '.' . $pair['from']) . ', ' . $this->quote($pair['to']) . ', ' . $this->quote('COLUMN');
        }

        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? ($blueprint->table . '_' . implode('_', $u['columns']) . '_unique');
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $this->wrap($name) . ' UNIQUE (' . $this->columnList($u['columns']) . ')';
        }
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? ($blueprint->table . '_' . implode('_', $i['columns']) . '_index');
            $stmts[] = 'CREATE INDEX ' . $this->wrap($name) . ' ON ' . $table . ' (' . $this->columnList($i['columns']) . ')';
        }
        foreach ($blueprint->dropUniqueNames as $n) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $this->wrap($n);
        }
        foreach ($blueprint->dropIndexNames as $n) {
            $stmts[] = 'DROP INDEX ' . $this->wrap($n) . ' ON ' . $table;
        }

        if ($blueprint->renameTo) {
            $stmts[] = 'EXEC sp_rename ' . $this->quote($blueprint->table) . ', ' . $this->quote($blueprint->renameTo);
        }

        return $stmts ?: ['-- no-op'];
    }

    private function compileColumn(ColumnDefinition $c): string
    {
        $type = match ($c->type) {
            'increments' => 'INT',
            'bigincrements' => 'BIGINT',
            'integer' => 'INT',
            'biginteger' => 'BIGINT',
            'string' => 'NVARCHAR(' . ($c->length ?? 255) . ')',
            'text' => 'NVARCHAR(MAX)',
            'boolean' => 'BIT',
            'json' => 'NVARCHAR(MAX)',
            'datetime' => 'DATETIME2',
            'decimal' => 'DECIMAL(' . ($c->precision ?? 8) . ',' . ($c->scale ?? 2) . ')',
            default => strtoupper($c->type),
        };

        $parts = [$this->wrap($c->name), $type];

        if (in_array($c->type, ['integer','biginteger','increments','bigincrements','decimal'], true) && $c->unsigned) {
            // SQL Server has no UNSIGNED integers; ignore.
        }

        if ($c->autoIncrement) {
            // IDENTITY(1,1) for auto-increment
            $parts[] = 'IDENTITY(1,1)';
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
        return '[' . str_replace([']'], [']]'], $identifier) . ']';
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_numeric($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

<?php

namespace Pairity\Schema\Grammars;

use Pairity\Schema\Blueprint;
use Pairity\Schema\ColumnDefinition;

class MySqlGrammar extends Grammar
{
    public function compileCreate(Blueprint $blueprint): array
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
            $inline[] = 'UNIQUE' . ($name ? ' ' . $this->wrap($name) : '') . ' (' . $this->columnList($u['columns']) . ')';
        }

        $definition = implode(",\n  ", array_merge($cols, $inline));
        $sql = 'CREATE TABLE ' . $this->wrap($blueprint->table) . " (\n  {$definition}\n)";

        // Indexes as separate statements
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

        // Add columns
        foreach ($blueprint->columns as $col) {
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->compileColumn($col);
        }

        // Drop columns
        foreach ($blueprint->dropColumns as $name) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $this->wrap($name);
        }

        // Rename columns
        foreach ($blueprint->renameColumns as $pair) {
            // MySQL 8+: RENAME COLUMN; older: CHANGE old new TYPE ...
            $stmts[] = 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $this->wrap($pair['from']) . ' TO ' . $this->wrap($pair['to']);
        }

        // Add uniques
        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? ($blueprint->table . '_' . implode('_', $u['columns']) . '_unique');
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $this->wrap($name) . ' UNIQUE (' . $this->columnList($u['columns']) . ')';
        }

        // Add indexes
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? ($blueprint->table . '_' . implode('_', $i['columns']) . '_index');
            $stmts[] = 'CREATE INDEX ' . $this->wrap($name) . ' ON ' . $table . ' (' . $this->columnList($i['columns']) . ')';
        }

        // Drop unique/index by name
        foreach ($blueprint->dropUniqueNames as $n) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $this->wrap($n);
        }
        foreach ($blueprint->dropIndexNames as $n) {
            $stmts[] = 'DROP INDEX ' . $this->wrap($n) . ' ON ' . $table;
        }

        // Rename table
        if ($blueprint->renameTo) {
            $stmts[] = 'RENAME TABLE ' . $table . ' TO ' . $this->wrap($blueprint->renameTo);
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
            'string' => 'VARCHAR(' . ($c->length ?? 255) . ')',
            'text' => 'TEXT',
            'boolean' => 'TINYINT(1)',
            'json' => 'JSON',
            'datetime' => 'DATETIME',
            'decimal' => 'DECIMAL(' . ($c->precision ?? 8) . ',' . ($c->scale ?? 2) . ')',
            default => strtoupper($c->type),
        };

        $parts = [$this->wrap($c->name), $type];
        if (in_array($c->type, ['integer','biginteger','increments','bigincrements','decimal'], true) && $c->unsigned) {
            $parts[] = 'UNSIGNED';
        }

        if ($c->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
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

    private function quoteDefault(mixed $value): string
    {
        if (is_numeric($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

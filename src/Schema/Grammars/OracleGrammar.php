<?php

namespace Pairity\Schema\Grammars;

use Pairity\Schema\Blueprint;
use Pairity\Schema\ColumnDefinition;

class OracleGrammar extends Grammar
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
            $inline[] = 'CONSTRAINT ' . ($name ? $this->wrap($name) : $this->wrap($this->makeName($blueprint->table, $u['columns'], 'uk'))) . ' UNIQUE (' . $this->columnList($u['columns']) . ')';
        }

        $definition = implode(",\n  ", array_merge($cols, $inline));
        $sql = 'CREATE TABLE ' . $this->wrap($blueprint->table) . " (\n  {$definition}\n)";

        $statements = [$sql];
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? $this->makeName($blueprint->table, $i['columns'], 'ix');
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
        // Oracle lacks IF EXISTS; use anonymous PL/SQL block
        $tbl = $this->wrap($table);
        $plsql = "BEGIN\n  EXECUTE IMMEDIATE 'DROP TABLE {$tbl}';\nEXCEPTION\n  WHEN OTHERS THEN\n    IF SQLCODE != -942 THEN RAISE; END IF;\nEND;";
        return [$plsql];
    }

    public function compileAlter(Blueprint $blueprint): array
    {
        $table = $this->wrap($blueprint->table);
        $stmts = [];

        // Add columns
        foreach ($blueprint->columns as $col) {
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD (' . $this->compileColumn($col) . ')';
        }

        // Drop columns
        foreach ($blueprint->dropColumns as $name) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $this->wrap($name);
        }

        // Rename columns
        foreach ($blueprint->renameColumns as $pair) {
            $stmts[] = 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $this->wrap($pair['from']) . ' TO ' . $this->wrap($pair['to']);
        }

        // Add uniques
        foreach ($blueprint->uniques as $u) {
            $name = $u['name'] ?? $this->makeName($blueprint->table, $u['columns'], 'uk');
            $stmts[] = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $this->wrap($name) . ' UNIQUE (' . $this->columnList($u['columns']) . ')';
        }

        // Add indexes
        foreach ($blueprint->indexes as $i) {
            $name = $i['name'] ?? $this->makeName($blueprint->table, $i['columns'], 'ix');
            $stmts[] = 'CREATE INDEX ' . $this->wrap($name) . ' ON ' . $table . ' (' . $this->columnList($i['columns']) . ')';
        }

        // Drop unique/index by name
        foreach ($blueprint->dropUniqueNames as $n) {
            $stmts[] = 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $this->wrap($n);
        }
        foreach ($blueprint->dropIndexNames as $n) {
            $stmts[] = 'DROP INDEX ' . $this->wrap($n);
        }

        // Rename table
        if ($blueprint->renameTo) {
            $stmts[] = 'ALTER TABLE ' . $table . ' RENAME TO ' . $this->wrap($blueprint->renameTo);
        }

        return $stmts ?: ['-- no-op'];
    }

    private function compileColumn(ColumnDefinition $c): string
    {
        $type = match ($c->type) {
            'increments' => 'NUMBER(10)',
            'bigincrements' => 'NUMBER(19)',
            'integer' => 'NUMBER(10)',
            'biginteger' => 'NUMBER(19)',
            'string' => 'VARCHAR2(' . ($c->length ?? 255) . ')',
            'text' => 'CLOB',
            'boolean' => 'NUMBER(1)', // store 0/1
            'json' => 'CLOB', // Oracle JSON type (21c+) not assumed; use CLOB
            'datetime' => 'TIMESTAMP',
            'decimal' => 'NUMBER(' . ($c->precision ?? 8) . ',' . ($c->scale ?? 2) . ')',
            default => strtoupper($c->type),
        };

        $parts = [$this->wrap($c->name), $type];

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

    private function makeName(string $table, array $columns, string $suffix): string
    {
        $base = $table . '_' . implode('_', $columns) . '_' . $suffix;
        // Oracle identifier max length is 30. Shorten deterministically if needed.
        if (strlen($base) <= 30) return $base;
        $hash = substr(sha1($base), 0, 8);
        $short = substr($table, 0, 10) . '_' . substr($columns[0] ?? 'col', 0, 5) . '_' . $suffix . '_' . $hash;
        return substr($short, 0, 30);
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_numeric($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';
        if ($value === null) return 'NULL';
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

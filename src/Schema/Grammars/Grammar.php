<?php

namespace Pairity\Schema\Grammars;

use Pairity\Schema\Blueprint;

abstract class Grammar
{
    /**
     * Compile a CREATE TABLE statement and any required additional index statements.
     * @return array<int,string> SQL statements to execute in order
     */
    abstract public function compileCreate(Blueprint $blueprint): array;

    /** @return array<int,string> */
    abstract public function compileDrop(string $table): array;

    /** @return array<int,string> */
    abstract public function compileDropIfExists(string $table): array;

    /**
     * Compile ALTER TABLE statements based on a Blueprint in alter mode.
     * @return array<int,string>
     */
    abstract public function compileAlter(\Pairity\Schema\Blueprint $blueprint): array;

    protected function wrap(string $identifier): string
    {
        // Default simple wrap with backticks; override in driver if different
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

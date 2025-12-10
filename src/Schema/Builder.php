<?php

namespace Pairity\Schema;

use Closure;
use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\Grammars\Grammar;
use Pairity\Schema\Grammars\SqliteGrammar;

class Builder
{
    private ConnectionInterface $connection;
    private Grammar $grammar;

    public function __construct(ConnectionInterface $connection, Grammar $grammar)
    {
        $this->connection = $connection;
        $this->grammar = $grammar;
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->create();
        $callback($blueprint);
        $this->run($this->grammar->compileCreate($blueprint));
    }

    public function drop(string $table): void
    {
        $this->run($this->grammar->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->run($this->grammar->compileDropIfExists($table));
    }

    /**
     * Alter an existing table using the blueprint alter helpers.
     */
    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->alter();
        $callback($blueprint);
        // If SQLite and operation requires rebuild on legacy versions, perform rebuild
        if ($this->grammar instanceof SqliteGrammar && ($blueprint->dropColumns || $blueprint->renameColumns)) {
            $version = $this->detectSqliteVersion();
            $needsRebuild = false;
            if ($blueprint->renameColumns) {
                // RENAME COLUMN requires >= 3.25
                $needsRebuild = $needsRebuild || version_compare($version, '3.25.0', '<');
            }
            if ($blueprint->dropColumns) {
                // DROP COLUMN requires >= 3.35
                $needsRebuild = $needsRebuild || version_compare($version, '3.35.0', '<');
            }
            if ($needsRebuild) {
                SqliteTableRebuilder::rebuild($this->connection, $blueprint, $this->grammar);
                return;
            }
        }

        $this->run($this->grammar->compileAlter($blueprint));
    }

    /** @param array<int,string> $sqls */
    private function run(array $sqls): void
    {
        foreach ($sqls as $sql) {
            $this->connection->execute($sql);
        }
    }

    private function detectSqliteVersion(): string
    {
        try {
            $rows = $this->connection->query('select sqlite_version() as v');
            $v = $rows[0]['v'] ?? '3.0.0';
            return is_string($v) ? $v : '3.0.0';
        } catch (\Throwable) {
            return '3.0.0';
        }
    }
}

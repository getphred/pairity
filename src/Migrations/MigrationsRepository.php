<?php

namespace Pairity\Migrations;

use Pairity\Contracts\ConnectionInterface;

class MigrationsRepository
{
    private ConnectionInterface $connection;
    private string $table;

    public function __construct(ConnectionInterface $connection, string $table = 'migrations')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function ensureTable(): void
    {
        // Portable table with string PK works across MySQL & SQLite
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            migration VARCHAR(255) PRIMARY KEY,
            batch INT NOT NULL,
            ran_at DATETIME NOT NULL
        )";
        $this->connection->execute($sql);
    }

    /**
     * @return array<int,string> migration names already ran
     */
    public function getRan(): array
    {
        $this->ensureTable();
        $rows = $this->connection->query("SELECT migration FROM {$this->table} ORDER BY migration ASC");
        return array_map(fn($r) => (string)$r['migration'], $rows);
    }

    public function getLastBatchNumber(): int
    {
        $this->ensureTable();
        $rows = $this->connection->query("SELECT MAX(batch) AS b FROM {$this->table}");
        $max = $rows[0]['b'] ?? 0;
        return (int)($max ?: 0);
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /** @return array<int,array{migration:string,batch:int,ran_at:string}> */
    public function getMigrationsInBatch(int $batch): array
    {
        $this->ensureTable();
        return $this->connection->query("SELECT migration, batch, ran_at FROM {$this->table} WHERE batch = :b ORDER BY migration DESC", ['b' => $batch]);
    }

    public function log(string $migration, int $batch): void
    {
        $this->ensureTable();
        $this->connection->execute(
            "INSERT INTO {$this->table} (migration, batch, ran_at) VALUES (:m, :b, :t)",
            ['m' => $migration, 'b' => $batch, 't' => gmdate('Y-m-d H:i:s')]
        );
    }

    public function remove(string $migration): void
    {
        $this->ensureTable();
        $this->connection->execute("DELETE FROM {$this->table} WHERE migration = :m", ['m' => $migration]);
    }
}

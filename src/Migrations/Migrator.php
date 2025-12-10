<?php

namespace Pairity\Migrations;

use Pairity\Contracts\ConnectionInterface;

class Migrator
{
    private ConnectionInterface $connection;
    private MigrationsRepository $repository;
    /** @var array<string,MigrationInterface> */
    private array $registry = [];

    public function __construct(ConnectionInterface $connection, ?MigrationsRepository $repository = null)
    {
        $this->connection = $connection;
        $this->repository = $repository ?? new MigrationsRepository($connection);
    }

    /**
     * Provide a registry (name => migration instance) used for rollback/reset resolution.
     *
     * @param array<string,MigrationInterface> $registry
     */
    public function setRegistry(array $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * Run outstanding migrations.
     *
     * @param array<string,MigrationInterface> $migrations An ordered map of name => instance
     * @return array<int,string> List of applied migration names
     */
    public function migrate(array $migrations): array
    {
        $this->repository->ensureTable();
        $ran = array_flip($this->repository->getRan());
        $batch = $this->repository->getNextBatchNumber();
        $applied = [];

        foreach ($migrations as $name => $migration) {
            if (isset($ran[$name])) {
                continue; // already ran
            }
            // keep in registry for potential rollback in the same process
            $this->registry[$name] = $migration;
            $this->connection->transaction(function () use ($migration, $name, $batch, &$applied) {
                $migration->up($this->connection);
                $this->repository->log($name, $batch);
                $applied[] = $name;
            });
        }

        return $applied;
    }

    /**
     * Roll back the last batch (or N steps of batches).
     *
     * @return array<int,string> List of rolled back migration names
     */
    public function rollback(int $steps = 1): array
    {
        $this->repository->ensureTable();
        $rolled = [];
        for ($i = 0; $i < $steps; $i++) {
            $batch = $this->repository->getLastBatchNumber();
            if ($batch <= 0) { break; }
            $items = $this->repository->getMigrationsInBatch($batch);
            if (!$items) { break; }
            foreach ($items as $row) {
                $name = (string)$row['migration'];
                $instance = $this->resolveMigration($name);
                if (!$instance) { continue; }
                $this->connection->transaction(function () use ($instance, $name, &$rolled) {
                    $instance->down($this->connection);
                    $this->repository->remove($name);
                    $rolled[] = $name;
                });
            }
        }
        return $rolled;
    }

    /**
     * Resolve a migration by name from registry or instantiate by class name.
     */
    private function resolveMigration(string $name): ?MigrationInterface
    {
        if (isset($this->registry[$name])) {
            return $this->registry[$name];
        }
        if (class_exists($name)) {
            $obj = new $name();
            if ($obj instanceof MigrationInterface) {
                return $obj;
            }
        }
        return null;
    }
}

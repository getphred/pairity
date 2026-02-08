<?php

declare(strict_types=1);

namespace Pairity\DAO;

use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\DTO\IdentityMap;

/**
 * Class BaseDAO
 *
 * Base class for all generated DAOs.
 *
 * @package Pairity\DAO
 */
abstract class BaseDAO
{
    /**
     * @var string The table name.
     */
    protected string $table;

    /**
     * @var string The primary key.
     */
    protected string $primaryKey = 'id';

    /**
     * @var string The connection name.
     */
    protected string $connection = 'default';

    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var \Pairity\Contracts\Schema\HydratorInterface|null
     */
    protected ?\Pairity\Contracts\Schema\HydratorInterface $hydrator = null;

    /**
     * @var string|null
     */
    protected ?string $dtoClass = null;

    /**
     * @var string|null
     */
    protected ?string $lockingColumn = null;

    /**
     * @var bool
     */
    protected bool $auditable = false;

    /**
     * BaseDAO constructor.
     *
     * @param DatabaseManagerInterface $db
     * @param IdentityMap $identityMap
     */
    public function __construct(
        protected DatabaseManagerInterface $db,
        protected IdentityMap $identityMap
    ) {
    }

    /**
     * Set the hydrator for the DAO.
     *
     * @param \Pairity\Contracts\Schema\HydratorInterface $hydrator
     * @return void
     */
    public function setHydrator(\Pairity\Contracts\Schema\HydratorInterface $hydrator): void
    {
        $this->hydrator = $hydrator;
    }

    /**
     * Get the connection instance.
     *
     * @return \Pairity\Contracts\Database\ConnectionInterface
     */
    protected function getConnection(): \Pairity\Contracts\Database\ConnectionInterface
    {
        return $this->db->connection($this->connection);
    }

    /**
     * Create a new query builder for the table.
     *
     * @return \Pairity\Database\Query\Builder
     */
    public function query(): \Pairity\Database\Query\Builder
    {
        $connection = $this->getConnection();
        $grammar = $this->db->getQueryGrammar($connection->getDriver()->getName());

        $builder = new \Pairity\Database\Query\Builder($this->db, $connection, $grammar);
        
        if ($this->dtoClass) {
            $builder->setModel($this->dtoClass, $this);
        }

        return $builder->from($this->table);
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connection;
    }

    /**
     * @return string|null
     */
    public function getDtoClass(): ?string
    {
        return $this->dtoClass;
    }

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get a DAO option.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @return \Pairity\Contracts\Schema\HydratorInterface|null
     */
    public function getHydrator(): ?\Pairity\Contracts\Schema\HydratorInterface
    {
        return $this->hydrator;
    }

    /**
     * @return IdentityMap
     */
    public function getIdentityMap(): IdentityMap
    {
        return $this->identityMap;
    }

    /**
     * Delete a record by its primary key.
     *
     * @param mixed $id
     * @return int
     */
    public function delete(mixed $id): int
    {
        return $this->getConnection()
            ->execute("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?", [$id]);
    }

    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param mixed $payload
     * @param bool $halt
     * @return mixed
     */
    protected function fireModelEvent(string $event, mixed $payload = null, bool $halt = true): mixed
    {
        $dispatcher = $this->db->getDispatcher();
        $fullEventName = "pairity.model.{$event}: " . get_class($payload ?? $this);

        return $dispatcher->dispatch($fullEventName, $payload, $halt);
    }

    /**
     * Get the database manager.
     *
     * @return DatabaseManagerInterface
     */
    public function getDb(): DatabaseManagerInterface
    {
        return $this->db;
    }

    /**
     * Save a DTO instance.
     *
     * @param \Pairity\DTO\BaseDTO $dto
     * @return bool
     * @throws \Pairity\Exceptions\DatabaseException
     */
    public function save(\Pairity\DTO\BaseDTO $dto): bool
    {
        $data = $dto->toArray();
        $primaryKeyValue = $data[$this->primaryKey] ?? null;

        if ($primaryKeyValue === null) {
            return $this->insert($data);
        }

        return $this->update($primaryKeyValue, $data, $dto);
    }

    /**
     * Insert a new record.
     *
     * @param array<string, mixed> $data
     * @return bool
     */
    protected function insert(array $data): bool
    {
        if ($this->getOption('tenancy', false)) {
            $tenantColumn = \Pairity\Database\Query\Scopes\TenantScope::getTenantColumn();
            if (!isset($data[$tenantColumn]) && ($tenantId = \Pairity\Database\Query\Scopes\TenantScope::getTenantId())) {
                $data[$tenantColumn] = $tenantId;
            }
        }

        if ($this->lockingColumn && !isset($data[$this->lockingColumn])) {
            $data[$this->lockingColumn] = 1;
        }

        $grammar = $this->db->getQueryGrammar($this->getConnection()->getDriver()->getName());
        $columns = implode(', ', array_map([$grammar, 'wrap'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        return $this->getConnection()->execute($sql, array_values($data)) > 0;
    }

    /**
     * Update an existing record.
     *
     * @param mixed $id
     * @param array<string, mixed> $data
     * @param \Pairity\DTO\BaseDTO|null $dto
     * @return bool
     * @throws \Pairity\Exceptions\DatabaseException
     */
    protected function update(mixed $id, array $data, ?\Pairity\DTO\BaseDTO $dto = null): bool
    {
        $sets = [];
        $values = [];
        $grammar = $this->db->getQueryGrammar($this->getConnection()->getDriver()->getName());
        
        $currentVersion = null;
        if ($this->lockingColumn) {
            $currentVersion = $data[$this->lockingColumn] ?? null;
            $data[$this->lockingColumn] = ($currentVersion ?: 1) + 1;
        }

        foreach ($data as $column => $value) {
            if ($column === $this->primaryKey) continue;
            $sets[] = $grammar->wrap($column) . " = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        $sql = "UPDATE " . $grammar->wrapTable($this->table) . " SET " . implode(', ', $sets) . " WHERE " . $grammar->wrap($this->primaryKey) . " = ?";

        if ($this->lockingColumn && $currentVersion !== null) {
            $sql .= " AND " . $grammar->wrap($this->lockingColumn) . " = ?";
            $values[] = $currentVersion;
        }

        $affected = $this->getConnection()->execute($sql, $values);

        if ($this->lockingColumn && $affected === 0) {
            throw new \Pairity\Exceptions\DatabaseException("Optimistic locking failed for table [{$this->table}] and ID [{$id}].");
        }

        if ($dto && $this->lockingColumn) {
            $dto->setAttribute($this->lockingColumn, $data[$this->lockingColumn]);
        }
        
        return $affected > 0;
    }
    /**
     * Update or insert records.
     *
     * @param array $values
     * @param array $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, array $uniqueBy, array $update = null): int
    {
        return $this->query()->upsert($values, $uniqueBy, $update);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class Blueprint
 *
 * Defines the state of a database table schema.
 *
 * @package Pairity\Schema
 */
class Blueprint
{
    /**
     * @var array<Column>
     */
    protected array $columns = [];

    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'prefix' => null,
        'tenancy' => false,
        'inheritance' => null,
        'morph' => null,
        'timestamps' => false,
        'softDeletes' => false,
        'auditable' => false,
        'view' => false,
        'locking' => false,
    ];

    /**
     * @var array<string, array<string>>
     */
    protected array $indexes = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $relations = [];

    /**
     * Blueprint constructor.
     *
     * @param string $tableName
     */
    public function __construct(
        protected string $tableName
    ) {
    }

    /**
     * Add a column to the blueprint.
     *
     * @param string $name
     * @param string $type
     * @param array<string, mixed> $parameters
     * @return Column
     */
    public function addColumn(string $name, string $type, array $parameters = []): Column
    {
        $column = new Column($name, $type, $parameters);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Get all columns.
     *
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the table name.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Check if the blueprint represents a database view.
     *
     * @return bool
     */
    public function isView(): bool
    {
        return (bool) $this->getOption('view', false);
    }

    /**
     * Set a table option.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Get a table option.
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
     * Get all options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Add an index to the table.
     *
     * @param string $name
     * @param array<string> $columns
     * @return void
     */
    public function addIndex(string $name, array $columns): void
    {
        $this->indexes[$name] = $columns;
    }

    /**
     * Get all indexes.
     *
     * @return array<string, array<string>>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Add a relation definition.
     *
     * @param string $name
     * @param array<string, mixed> $definition
     * @return void
     */
    public function addRelation(string $name, array $definition): void
    {
        $this->relations[$name] = $definition;
    }

    /**
     * Get all relations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class Builder
 *
 * Provides a fluent API for defining database table schemas.
 *
 * @package Pairity\Schema
 */
class Builder
{
    /**
     * @var Blueprint
     */
    protected Blueprint $blueprint;

    /**
     * Builder constructor.
     *
     * @param string $tableName
     * @param TypeMapper $typeMapper
     */
    public function __construct(
        string $tableName,
        protected TypeMapper $typeMapper = new TypeMapper()
    ) {
        $this->blueprint = new Blueprint($tableName);
    }

    /**
     * Add an auto-incrementing big integer primary key.
     *
     * @param string $name
     * @return Column
     */
    public function id(string $name = 'id'): Column
    {
        return $this->bigInteger($name)->primary()->unsigned();
    }

    /**
     * Add a string column.
     *
     * @param string $name
     * @param int|null $length
     * @return Column
     */
    public function string(string $name, ?int $length = null): Column
    {
        $parameters = $length ? ['length' => $length] : [];
        return $this->addColumn($name, 'string', $parameters);
    }

    /**
     * Add a text column.
     *
     * @param string $name
     * @return Column
     */
    public function text(string $name): Column
    {
        return $this->addColumn($name, 'text');
    }

    /**
     * Add an integer column.
     *
     * @param string $name
     * @return Column
     */
    public function integer(string $name): Column
    {
        return $this->addColumn($name, 'integer');
    }

    /**
     * Add a big integer column.
     *
     * @param string $name
     * @return Column
     */
    public function bigInteger(string $name): Column
    {
        return $this->addColumn($name, 'bigInteger');
    }

    /**
     * Add a boolean column.
     *
     * @param string $name
     * @return Column
     */
    public function boolean(string $name): Column
    {
        return $this->addColumn($name, 'boolean');
    }

    /**
     * Add timestamps (created_at and updated_at).
     *
     * @return void
     */
    public function timestamps(): void
    {
        $this->blueprint->setOption('timestamps', true);
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a timestamp column.
     *
     * @param string $name
     * @return Column
     */
    public function timestamp(string $name): Column
    {
        return $this->addColumn($name, 'timestamp');
    }

    /**
     * Add soft deletes column.
     *
     * @return void
     */
    public function softDeletes(): void
    {
        $this->blueprint->setOption('softDeletes', true);
        $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Add a generic column.
     *
     * @param string $name
     * @param string $type
     * @param array<string, mixed> $parameters
     * @return Column
     */
    public function addColumn(string $name, string $type, array $parameters = []): Column
    {
        $typeProps = $this->typeMapper->getProperties($type);
        $parameters = array_merge($typeProps, $parameters);
        
        return $this->blueprint->addColumn($name, $type, $parameters);
    }

    /**
     * Get the underlying blueprint.
     *
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint
    {
        return $this->blueprint;
    }

    /**
     * Magic method to support other types from TypeMapper.
     *
     * @param string $method
     * @param array<mixed> $args
     * @return Column
     */
    public function __call(string $method, array $args): Column
    {
        if ($this->typeMapper->has($method)) {
            return $this->addColumn($args[0], $method, $args[1] ?? []);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on " . static::class);
    }
}

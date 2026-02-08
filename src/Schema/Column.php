<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class Column
 *
 * Represents a single column in a database table schema.
 *
 * @package Pairity\Schema
 */
class Column
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [
        'nullable' => false,
        'default' => null,
        'unique' => false,
        'primary' => false,
        'index' => false,
        'comment' => null,
        'encrypted' => false,
        'unsigned' => false,
        'autoIncrement' => false,
    ];

    /**
     * Column constructor.
     *
     * @param string $name
     * @param string $type
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        protected string $name,
        protected string $type,
        array $parameters = []
    ) {
        $this->attributes = array_merge($this->attributes, $parameters);
    }

    /**
     * Set the column to be nullable.
     *
     * @param bool $value
     * @return $this
     */
    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    /**
     * Set the default value for the column.
     *
     * @param mixed $value
     * @return $this
     */
    public function default(mixed $value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    /**
     * Set the column to be unique.
     *
     * @param bool $value
     * @return $this
     */
    public function unique(bool $value = true): self
    {
        $this->attributes['unique'] = $value;
        return $this;
    }

    /**
     * Set the column as part of the primary key.
     *
     * @param bool $value
     * @return $this
     */
    public function primary(bool $value = true): self
    {
        $this->attributes['primary'] = $value;
        return $this;
    }

    /**
     * Set the column to be encrypted.
     *
     * @param bool $value
     * @return $this
     */
    public function encrypted(bool $value = true): self
    {
        $this->attributes['encrypted'] = $value;
        return $this;
    }

    /**
     * Set the column to be an index.
     *
     * @param bool $value
     * @return $this
     */
    public function index(bool $value = true): self
    {
        $this->attributes['index'] = $value;
        return $this;
    }

    /**
     * Set the column to be unsigned.
     *
     * @param bool $value
     * @return $this
     */
    public function unsigned(bool $value = true): self
    {
        $this->attributes['unsigned'] = $value;
        return $this;
    }

    /**
     * Set the column to auto-increment.
     *
     * @param bool $value
     * @return $this
     */
    public function autoIncrement(bool $value = true): self
    {
        $this->attributes['autoIncrement'] = $value;
        return $this;
    }

    /**
     * Get the column name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the column type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get all column attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}

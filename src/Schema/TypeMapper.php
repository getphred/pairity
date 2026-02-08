<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class TypeMapper
 *
 * Maps abstract schema types to database-agnostic properties and defaults.
 *
 * @package Pairity\Schema
 */
class TypeMapper
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $types = [
        'string' => ['type' => 'string', 'length' => 255],
        'text' => ['type' => 'text'],
        'integer' => ['type' => 'integer'],
        'bigInteger' => ['type' => 'bigInteger'],
        'float' => ['type' => 'float'],
        'decimal' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
        'boolean' => ['type' => 'boolean'],
        'date' => ['type' => 'date'],
        'datetime' => ['type' => 'datetime'],
        'timestamp' => ['type' => 'timestamp'],
        'json' => ['type' => 'json'],
        'binary' => ['type' => 'binary'],
        'uuid' => ['type' => 'uuid'],
        'ulid' => ['type' => 'ulid'],
        'enum' => ['type' => 'enum'],
    ];

    /**
     * Get the mapped properties for a type.
     *
     * @param string $type
     * @return array<string, mixed>
     */
    public function getProperties(string $type): array
    {
        return $this->types[$type] ?? ['type' => $type];
    }

    /**
     * Register a custom type mapping.
     *
     * @param string $name
     * @param array<string, mixed> $properties
     * @return void
     */
    public function register(string $name, array $properties): void
    {
        $this->types[$name] = $properties;
    }

    /**
     * Check if a type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }
}

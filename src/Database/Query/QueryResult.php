<?php

declare(strict_types=1);

namespace Pairity\Database\Query;

/**
 * Class QueryResult
 *
 * A lightweight object wrapper for raw Query Builder results.
 * Provides magic property and method access for a consistent developer experience.
 */
class QueryResult
{
    /**
     * QueryResult constructor.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected array $attributes = []
    ) {
    }

    /**
     * Magic property access.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Magic method access for getters (e.g., getEmail()).
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get')) {
            $property = lcfirst(substr($name, 3));
            
            if (array_key_exists($property, $this->attributes)) {
                return $this->attributes[$property];
            }

            // Also try snake_case if it matches common DB conventions
            $snakeProperty = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
            if (array_key_exists($snakeProperty, $this->attributes)) {
                return $this->attributes[$snakeProperty];
            }
        }

        return null;
    }

    /**
     * Convert the result to a raw array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Check if an attribute exists.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
}

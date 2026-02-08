<?php

declare(strict_types=1);

namespace Pairity\DTO;

use WeakMap;

/**
 * Class IdentityMap
 * 
 * Tracks DTO instances by their class and primary key to maintain object identity.
 * Uses a WeakMap to allow garbage collection of DTOs not referenced elsewhere.
 */
class IdentityMap
{
    /**
     * @var array<string, WeakMap<object, bool>>
     */
    protected array $map = [];

    /**
     * @var array<string, array<string|int, object>>
     */
    protected array $strongRefs = [];

    /**
     * Get a DTO from the map.
     *
     * @param string $className
     * @param string|int $id
     * @return object|null
     */
    public function get(string $className, string|int $id): ?object
    {
        return $this->strongRefs[$className][$id] ?? null;
    }

    /**
     * Add a DTO to the map.
     *
     * @param string $className
     * @param string|int $id
     * @param object $instance
     * @return void
     */
    public function add(string $className, string|int $id, object $instance): void
    {
        if (!isset($this->strongRefs[$className])) {
            $this->strongRefs[$className] = [];
        }

        $this->strongRefs[$className][$id] = $instance;
    }

    /**
     * Clear the identity map.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->strongRefs = [];
    }
}

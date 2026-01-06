<?php

namespace Pairity\Contracts;

use Psr\SimpleCache\CacheInterface;

interface CacheableDaoInterface extends DaoInterface
{
    /**
     * Set the cache instance for this DAO.
     */
    public function setCache(CacheInterface $cache): static;

    /**
     * Get the cache instance.
     */
    public function getCache(): ?CacheInterface;

    /**
     * Get cache configuration (enabled, ttl, prefix).
     *
     * @return array{enabled: bool, ttl: ?int, prefix: string}
     */
    public function cacheConfig(): array;

    /**
     * Clear all cache entries related to this DAO/Table.
     */
    public function clearCache(): bool;
}

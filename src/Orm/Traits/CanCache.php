<?php

namespace Pairity\Orm\Traits;

use Psr\SimpleCache\CacheInterface;

trait CanCache
{
    protected ?CacheInterface $cache = null;

    /**
     * @see \Pairity\Contracts\CacheableDaoInterface::setCache
     */
    public function setCache(CacheInterface $cache): static
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @see \Pairity\Contracts\CacheableDaoInterface::getCache
     */
    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }

    /**
     * @see \Pairity\Contracts\CacheableDaoInterface::cacheConfig
     */
    public function cacheConfig(): array
    {
        return [
            'enabled' => true,
            'ttl' => 3600,
            'prefix' => 'pairity_cache_' . $this->getTable() . '_',
        ];
    }

    /**
     * @see \Pairity\Contracts\CacheableDaoInterface::clearCache
     */
    public function clearCache(): bool
    {
        if ($this->cache === null) {
            return false;
        }

        $config = $this->cacheConfig();
        if (!$config['enabled']) {
            return false;
        }

        // PSR-16 doesn't have a flush by prefix. 
        // If the cache is an instance of something that can clear, we can try.
        // But for standard PSR-16, we often just clear() everything if it's a dedicated pool,
        // however that's too destructive.
        
        // Strategy: We'll allow users to override this method if their driver supports tags/prefixes.
        // For now, we'll try to use clear() if we are reasonably sure it's safe (e.g. via config opt-in).
        if ($config['clear_all_on_bulk'] ?? false) {
            return $this->cache->clear();
        }

        return false;
    }

    /**
     * Generate a cache key for a specific ID.
     */
    protected function getCacheKeyForId(mixed $id): string
    {
        $config = $this->cacheConfig();
        return $config['prefix'] . 'id_' . $id;
    }

    /**
     * Generate a cache key for criteria.
     */
    protected function getCacheKeyForCriteria(array $criteria): string
    {
        $config = $this->cacheConfig();
        // Naive serialization, might need better normalization
        return $config['prefix'] . 'criteria_' . md5(serialize($criteria));
    }

    /**
     * Store an item in the cache if enabled.
     */
    protected function putInCache(string $key, mixed $value): void
    {
        if ($this->cache === null) {
            return;
        }

        $config = $this->cacheConfig();
        if (!$config['enabled']) {
            return;
        }

        $this->cache->set($key, $value, $config['ttl']);
    }

    /**
     * Retrieve an item from the cache if enabled.
     */
    protected function getFromCache(string $key): mixed
    {
        if ($this->cache === null) {
            return null;
        }

        $config = $this->cacheConfig();
        if (!$config['enabled']) {
            return null;
        }

        return $this->cache->get($key);
    }

    /**
     * Remove an item from the cache.
     */
    protected function removeFromCache(string $key): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->delete($key);
    }
}

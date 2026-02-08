<?php

declare(strict_types=1);

namespace Pairity\Cache;

use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Class FileCache
 *
 * A simple PSR-16 compliant file-based cache implementation.
 *
 * @package Pairity\Cache
 */
class FileCache implements CacheInterface
{
    /**
     * FileCache constructor.
     *
     * @param string $directory The directory to store cache files.
     */
    public function __construct(
        protected string $directory
    ) {
        $this->ensureDirectoryExists();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        try {
            $data = unserialize($content);
        } catch (\Throwable) {
            return $default;
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $expiresAt = $this->calculateExpiry($ttl);
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $files = glob($this->directory . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Get the file path for a cache key.
     *
     * @param string $key
     * @return string
     */
    protected function getFilePath(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Calculate the expiry timestamp.
     *
     * @param \DateInterval|int|null $ttl
     * @return int|null
     */
    protected function calculateExpiry(\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp();
        }

        return time() + $ttl;
    }

    /**
     * Ensure the cache directory exists and is writable.
     *
     * @return void
     * @throws RuntimeException
     */
    protected function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
                throw new RuntimeException("Directory [{$this->directory}] was not created.");
            }
        }

        if (!is_writable($this->directory)) {
            throw new RuntimeException("Directory [{$this->directory}] is not writable.");
        }
    }
}

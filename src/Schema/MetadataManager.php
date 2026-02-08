<?php

declare(strict_types=1);

namespace Pairity\Schema;

use Pairity\Exceptions\SchemaException;
use Psr\SimpleCache\CacheInterface;

/**
 * Class MetadataManager
 *
 * Manages the retrieval and caching of table blueprints.
 *
 * @package Pairity\Schema
 */
class MetadataManager
{
    /**
     * @var array<string, Blueprint> In-memory registry to avoid repeated lookups in a single request.
     */
    protected array $registry = [];

    /**
     * MetadataManager constructor.
     *
     * @param YamlSchemaParser $parser
     * @param CacheInterface|null $cache
     */
    public function __construct(
        protected YamlSchemaParser $parser,
        protected ?CacheInterface $cache = null
    ) {
    }

    /**
     * Get a blueprint for a given table, using cache if available.
     *
     * @param string $tableName
     * @param string $schemaPath Path to the schema directory or specific YAML file.
     * @return Blueprint
     * @throws SchemaException
     */
    public function getBlueprint(string $tableName, string $schemaPath): Blueprint
    {
        if (isset($this->registry[$tableName])) {
            return $this->registry[$tableName];
        }

        $filePath = $this->resolveFilePath($tableName, $schemaPath);
        $cacheKey = "blueprint.{$tableName}." . md5($filePath);

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached && $this->isValid($cached, $filePath)) {
                return $this->registry[$tableName] = $cached['blueprint'];
            }
        }

        $blueprint = $this->parser->parseFile($filePath);

        if ($this->cache) {
            $this->cache->set($cacheKey, [
                'blueprint' => $blueprint,
                'hash' => md5_file($filePath),
                'mtime' => filemtime($filePath),
            ]);
        }

        return $this->registry[$tableName] = $blueprint;
    }

    /**
     * Clear the metadata cache.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        $this->registry = [];
        return $this->cache ? $this->cache->clear() : true;
    }

    /**
     * Resolve the full file path for a table schema.
     *
     * @param string $tableName
     * @param string $schemaPath
     * @return string
     * @throws SchemaException
     */
    protected function resolveFilePath(string $tableName, string $schemaPath): string
    {
        if (is_file($schemaPath)) {
            return $schemaPath;
        }

        $filePath = rtrim($schemaPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tableName . '.yaml';

        if (!file_exists($filePath)) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new SchemaException(
                $translator->trans('error.schema_file_not_found', ['path' => $filePath]),
                0,
                null,
                ['path' => $filePath, 'table' => $tableName]
            );
        }

        return $filePath;
    }

    /**
     * Check if the cached blueprint is still valid.
     *
     * @param array $cached
     * @param string $filePath
     * @return bool
     */
    protected function isValid(array $cached, string $filePath): bool
    {
        if (!isset($cached['hash']) || !isset($cached['mtime'])) {
            return false;
        }

        // Fast check: modification time
        if (filemtime($filePath) === $cached['mtime']) {
            return true;
        }

        // Slow check: hash
        return md5_file($filePath) === $cached['hash'];
    }
}

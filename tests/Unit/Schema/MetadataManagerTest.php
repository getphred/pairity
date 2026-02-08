<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Schema;

use Pairity\Schema\MetadataManager;
use Pairity\Schema\YamlSchemaParser;
use Pairity\Schema\Blueprint;
use Pairity\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class MetadataManagerTest extends TestCase
{
    protected string $schemaDir;
    protected string $cacheDir;
    protected MetadataManager $manager;
    protected YamlSchemaParser $parser;
    protected FileCache $cache;

    protected function setUp(): void
    {
        $this->schemaDir = __DIR__ . '/../../../schema_test';
        $this->cacheDir = __DIR__ . '/../../../storage/test_metadata_cache';
        
        if (!is_dir($this->schemaDir)) mkdir($this->schemaDir, 0755, true);
        if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0755, true);

        $this->parser = new YamlSchemaParser();
        $this->cache = new FileCache($this->cacheDir);
        $this->manager = new MetadataManager($this->parser, $this->cache);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->schemaDir);
        $this->removeDir($this->cacheDir);
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function test_it_parses_and_caches_blueprint()
    {
        $yaml = "columns:\n  id: bigInteger\n";
        file_put_contents($this->schemaDir . '/users.yaml', $yaml);

        $blueprint = $this->manager->getBlueprint('users', $this->schemaDir);
        $this->assertInstanceOf(Blueprint::class, $blueprint);
        $this->assertEquals('users', $blueprint->getTableName());

        // Second call should come from memory registry
        $blueprint2 = $this->manager->getBlueprint('users', $this->schemaDir);
        $this->assertSame($blueprint, $blueprint2);

        // Verify it is in cache
        $newManager = new MetadataManager($this->parser, $this->cache);
        $blueprint3 = $newManager->getBlueprint('users', $this->schemaDir);
        $this->assertEquals($blueprint->getTableName(), $blueprint3->getTableName());
        $this->assertNotSame($blueprint, $blueprint3); // Different instance from cache
    }

    public function test_it_invalidates_cache_on_file_change()
    {
        $yaml = "columns:\n  id: bigInteger\n";
        $file = $this->schemaDir . '/users.yaml';
        file_put_contents($file, $yaml);

        $blueprint = $this->manager->getBlueprint('users', $this->schemaDir);
        
        // Change file
        sleep(1); // Ensure mtime change
        $yaml2 = "columns:\n  id: bigInteger\n  name: string\n";
        file_put_contents($file, $yaml2);

        $newManager = new MetadataManager($this->parser, $this->cache);
        $blueprint2 = $newManager->getBlueprint('users', $this->schemaDir);
        
        $this->assertCount(2, $blueprint2->getColumns());
        $this->assertNotEquals($blueprint->getColumns(), $blueprint2->getColumns());
    }
}

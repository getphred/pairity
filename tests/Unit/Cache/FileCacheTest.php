<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Cache;

use Pairity\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    protected string $cacheDir;
    protected FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . '/../../../storage/test_cache';
        if (is_dir($this->cacheDir)) {
            $this->removeDir($this->cacheDir);
        }
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $this->removeDir($this->cacheDir);
        }
    }

    protected function removeDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function test_it_can_set_and_get_values()
    {
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertEquals('bar', $this->cache->get('foo'));
    }

    public function test_it_returns_default_for_missing_keys()
    {
        $this->assertEquals('default', $this->cache->get('missing', 'default'));
    }

    public function test_it_can_delete_values()
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->delete('foo'));
        $this->assertNull($this->cache->get('foo'));
    }

    public function test_it_can_clear_cache()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('baz', 'qux');
        $this->assertTrue($this->cache->clear());
        $this->assertNull($this->cache->get('foo'));
        $this->assertNull($this->cache->get('baz'));
    }

    public function test_it_handles_expiration()
    {
        $this->cache->set('expired', 'value', -1);
        $this->assertNull($this->cache->get('expired'));
    }

    public function test_it_can_set_and_get_multiple()
    {
        $values = ['a' => 1, 'b' => 2];
        $this->assertTrue($this->cache->setMultiple($values));
        $this->assertEquals($values, $this->cache->getMultiple(['a', 'b']));
    }
}

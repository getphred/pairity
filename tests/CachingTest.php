<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;
use Pairity\Orm\UnitOfWork;
use Psr\SimpleCache\CacheInterface;

class TestDto extends AbstractDto {}

final class CachingTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    private function mockCache()
    {
        return new class implements CacheInterface {
            private array $store = [];
            public $hits = 0;
            public $sets = 0;
            public function get(string $key, mixed $default = null): mixed {
                if (isset($this->store[$key])) {
                    $this->hits++;
                    return unserialize($this->store[$key]);
                }
                return $default;
            }
            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool {
                $this->sets++;
                $this->store[$key] = serialize($value);
                return true;
            }
            public function delete(string $key): bool { unset($this->store[$key]); return true; }
            public function clear(): bool { $this->store = []; return true; }
            public function getMultiple(iterable $keys, mixed $default = null): iterable { return []; }
            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool { return true; }
            public function deleteMultiple(iterable $keys): bool { return true; }
            public function has(string $key): bool { return isset($this->store[$key]); }
        };
    }

    public function testCachingFindById(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        
        $dao = new class($conn) extends AbstractDao {
            public function getTable(): string { return 'items'; }
            protected function dtoClass(): string { return TestDto::class; }
        };

        $cache = $this->mockCache();
        $dao->setCache($cache);

        $created = $dao->insert(['name' => 'Item 1']);
        $id = $created->id;
        // insert() calls findById() internally to return the fresh DTO, so it might already be cached.
        
        $cache->hits = 0; // Reset hits for the test

        // First find - should be a cache hit now because of insert()
        $item1 = $dao->findById($id);
        $this->assertNotNull($item1);
        $this->assertEquals(1, $cache->hits);

        // Second find - cache hit again
        $item2 = $dao->findById($id);
        $this->assertNotNull($item2);
        $this->assertEquals(2, $cache->hits);
        $this->assertEquals($item1->name, $item2->name);
        // Note: item1 and item2 are different instances if not in UoW, 
        // because we serialize/unserialize in our mock cache.
        $this->assertNotSame($item1, $item2);

        // Update - should invalidate cache
        $dao->update($id, ['name' => 'Updated']);
        $item3 = $dao->findById($id);
        // How many hits now? 
        // findById checks cache for ID (miss)
        // findById calls findOneBy
        // findOneBy checks cache for criteria (miss)
        $this->assertEquals('Updated', $item3->name);
    }

    public function testIdentityMapIntegration(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        
        $dao = new class($conn) extends AbstractDao {
            public function getTable(): string { return 'items'; }
            protected function dtoClass(): string { return TestDto::class; }
        };

        $cache = $this->mockCache();
        $dao->setCache($cache);

        $created = $dao->insert(['name' => 'Item 1']);
        $id = $created->id;
        $cache->hits = 0;

        UnitOfWork::run(function() use ($dao, $id, $cache) {
            $item1 = $dao->findById($id); // Cache hit (from insert), attaches to identity map
            $item2 = $dao->findById($id); // UoW lookup (no cache hit recorded)
            $this->assertEquals(1, $cache->hits);
            $this->assertSame($item1, $item2);
        });

        // Outside UoW
        $item3 = $dao->findById($id); // Cache hit
        $this->assertEquals(2, $cache->hits);

        // Run another UoW, should hit cache but then return same instance from UoW
        UnitOfWork::run(function() use ($dao, $id, $cache) {
            $item4 = $dao->findById($id); // Cache hit, attached to UoW
            $this->assertEquals(3, $cache->hits);
            $item5 = $dao->findById($id); // UoW hit
            $this->assertEquals(3, $cache->hits);
            $this->assertSame($item4, $item5);
        });
    }

    public function testFindAllCaching(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        
        $dao = new class($conn) extends AbstractDao {
            public function getTable(): string { return 'items'; }
            protected function dtoClass(): string { return TestDto::class; }
        };

        $cache = $this->mockCache();
        $dao->setCache($cache);

        $dao->insert(['name' => 'A']);
        $dao->insert(['name' => 'B']);

        $all1 = $dao->findAllBy([]);
        $this->assertCount(2, $all1);
        $this->assertEquals(0, $cache->hits);

        $all2 = $dao->findAllBy([]);
        $this->assertCount(2, $all2);
        $this->assertEquals(1, $cache->hits);

        // Test bulk invalidation
        $dao->deleteBy(['name' => 'A']); // In our trait, this does nothing unless clear_all_on_bulk is true
        
        // Let's configure it to clear all
        $dao = new class($conn) extends AbstractDao {
            public function getTable(): string { return 'items'; }
            protected function dtoClass(): string { return TestDto::class; }
            public function cacheConfig(): array {
                return array_merge(parent::cacheConfig(), ['clear_all_on_bulk' => true]);
            }
        };
        $dao->setCache($cache);
        
        $dao->findAllBy([]); // missed again because it's a new DAO instance/key prefix
        $dao->findAllBy([]); // hit
        $this->assertGreaterThanOrEqual(1, $cache->hits);
        
        $dao->deleteBy(['name' => 'A']);
        $all3 = $dao->findAllBy([]); // miss (invalidated)
        $this->assertCount(1, $all3);
    }
}

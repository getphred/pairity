<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;

/**
 * @group mongo-integration
 */
final class MongoDaoTest extends TestCase
{
    private function hasMongoExt(): bool
    {
        return extension_loaded('mongodb');
    }

    public function testCrudViaDao(): void
    {
        if (!$this->hasMongoExt()) {
            $this->markTestSkipped('ext-mongodb not loaded');
        }

        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Ping server to ensure availability
        try {
            $conn->getClient()->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Define DTO/DAO inline for test
        $dtoClass = new class([]) extends AbstractDto {};
        $dtoFqcn = get_class($dtoClass);

        $dao = new class($conn, $dtoFqcn) extends AbstractMongoDao {
            private string $dto;
            public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.widgets'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        // Clean collection
        foreach ($dao->findAllBy([]) as $doc) {
            $id = (string)($doc->toArray(false)['_id'] ?? '');
            if ($id !== '') { $dao->deleteById($id); }
        }

        // Insert
        $created = $dao->insert(['name' => 'Widget', 'qty' => 5, 'tags' => ['a','b']]);
        $arr = $created->toArray(false);
        $this->assertArrayHasKey('_id', $arr);
        $id = (string)$arr['_id'];
        $this->assertNotEmpty($id);

        // Find by id
        $found = $dao->findById($id);
        $this->assertNotNull($found);
        $this->assertSame('Widget', $found->toArray(false)['name'] ?? null);

        // Update
        $updated = $dao->update($id, ['qty' => 7]);
        $this->assertSame(7, $updated->toArray(false)['qty'] ?? null);

        // Projection, sorting, limit/skip
        $list = $dao->fields('name')->sort(['name' => 1])->limit(10)->skip(0)->findAllBy([]);
        $this->assertNotEmpty($list);
        $this->assertArrayHasKey('name', $list[0]->toArray(false));

        // Dynamic helper findOneByName
        $one = $dao->findOneByName('Widget');
        $this->assertNotNull($one);

        // Delete
        $deleted = $dao->deleteById($id);
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertNull($dao->findById($id));
    }
}

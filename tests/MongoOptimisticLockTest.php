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
final class MongoOptimisticLockTest extends TestCase
{
    private function hasMongoExt(): bool { return \extension_loaded('mongodb'); }

    public function testVersionIncrementOnUpdate(): void
    {
        if (!$this->hasMongoExt()) { $this->markTestSkipped('ext-mongodb not loaded'); }

        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
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

        $dto = new class([]) extends AbstractDto {};
        $dtoClass = \get_class($dto);

        $Dao = new class($conn, $dtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c, string $d){ parent::__construct($c); $this->dto=$d; }
            protected function collection(): string { return 'pairity_test.lock_users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function locking(): array { return ['type' => 'version', 'column' => 'version']; }
        };

        $dao = new $Dao($conn, $dtoClass);

        // Clean
        foreach ($dao->findAllBy([]) as $doc) { $id = (string)($doc->toArray(false)['_id'] ?? ''); if ($id) { $dao->deleteById($id); } }

        // Insert with initial version 0
        $created = $dao->insert(['email' => 'lock@example.com', 'version' => 0]);
        $id = (string)($created->toArray(false)['_id'] ?? '');
        $this->assertNotEmpty($id);

        // Update should bump version to 1
        $dao->update($id, ['email' => 'lock2@example.com']);
        $after = $dao->findById($id);
        $this->assertNotNull($after);
        $this->assertSame(1, (int)($after->toArray(false)['version'] ?? -1));
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Orm\UnitOfWork;
use Pairity\Model\AbstractDto;

final class UnitOfWorkMongoTest extends TestCase
{
    private function hasMongoExt(): bool
    {
        return \extension_loaded('mongodb');
    }

    public function testDeferredUpdateAndDeleteCommit(): void
    {
        if (!$this->hasMongoExt()) {
            $this->markTestSkipped('ext-mongodb not loaded');
        }

        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Inline DTO and DAO
        $dto = new class([]) extends AbstractDto {};
        $dtoClass = \get_class($dto);

        $dao = new class($conn, $dtoClass) extends AbstractMongoDao {
            private string $dto;
            public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.uow_docs'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        // Clean collection
        foreach ($dao->findAllBy([]) as $doc) {
            $id = (string)($doc->toArray(false)['_id'] ?? '');
            if ($id !== '') { $dao->deleteById($id); }
        }

        // Insert a document (immediate)
        $created = $dao->insert(['name' => 'Widget', 'qty' => 1]);
        $id = (string)($created->toArray(false)['_id'] ?? '');
        $this->assertNotEmpty($id);

        // Run UoW with deferred update then delete
        UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
            $one = $dao->findById($id);
            $this->assertNotNull($one);
            // defer update
            $dao->update($id, ['qty' => 2]);
            // defer delete
            $dao->deleteById($id);
            // commit at end of run()
        });

        // After commit, it should be deleted
        $this->assertNull($dao->findById($id));
    }

    public function testRollbackOnExceptionClearsOps(): void
    {
        if (!$this->hasMongoExt()) {
            $this->markTestSkipped('ext-mongodb not loaded');
        }

        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        $dto = new class([]) extends AbstractDto {};
        $dtoClass = \get_class($dto);

        $dao = new class($conn, $dtoClass) extends AbstractMongoDao {
            private string $dto;
            public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.uow_docs'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        // Clean
        foreach ($dao->findAllBy([]) as $doc) {
            $id = (string)($doc->toArray(false)['_id'] ?? '');
            if ($id !== '') { $dao->deleteById($id); }
        }

        // Insert and capture id
        $created = $dao->insert(['name' => 'Widget', 'qty' => 1]);
        $id = (string)($created->toArray(false)['_id'] ?? '');

        // Attempt a UoW that throws
        try {
            UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
                $dao->update($id, ['qty' => 99]);
                throw new \RuntimeException('boom');
            });
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            // expected
        }

        // Update should not have been applied due to rollback
        $after = $dao->findById($id);
        $this->assertSame(1, $after?->toArray(false)['qty'] ?? null);
    }
}

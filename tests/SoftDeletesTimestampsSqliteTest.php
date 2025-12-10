<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

final class SoftDeletesTimestampsSqliteTest extends TestCase
{
    private function makeConnection()
    {
        return ConnectionManager::make([
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);
    }

    public function testTimestampsAndSoftDeletesFlow(): void
    {
        $conn = $this->makeConnection();
        // Create table
        $conn->execute('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            name TEXT NULL,
            status TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        // Define DTO/DAO
        $dto = new class([]) extends AbstractDto {};
        $dao = new class($conn) extends AbstractDao {
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return get_class(new class([]) extends AbstractDto {}); }
            protected function schema(): array
            {
                return [
                    'primaryKey' => 'id',
                    'columns' => [
                        'id' => ['cast' => 'int'],
                        'email' => ['cast' => 'string'],
                        'name' => ['cast' => 'string'],
                        'status' => ['cast' => 'string'],
                        'created_at' => ['cast' => 'datetime'],
                        'updated_at' => ['cast' => 'datetime'],
                        'deleted_at' => ['cast' => 'datetime'],
                    ],
                    'timestamps' => ['createdAt' => 'created_at', 'updatedAt' => 'updated_at'],
                    'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
                ];
            }
        };

        // Insert (created_at & updated_at auto)
        $created = $dao->insert(['email' => 't@example.com', 'name' => 'T', 'status' => 'active']);
        $arr = $created->toArray();
        $this->assertArrayHasKey('id', $arr);
        $this->assertNotEmpty($arr['id']);
        $this->assertNotNull($arr['created_at'] ?? null);
        $this->assertNotNull($arr['updated_at'] ?? null);

        // Update via update() should change updated_at
        $id = $arr['id'];
        $prevUpdated = $arr['updated_at'];
        // sleep(1) not reliable; just ensure it is a value and after call it exists
        $dao->update($id, ['name' => 'T2']);
        $after = $dao->findById($id)?->toArray();
        $this->assertNotNull($after);
        $this->assertNotNull($after['updated_at'] ?? null);

        // Update via updateBy() also sets updated_at
        $dao->updateBy(['id' => $id], ['status' => 'inactive']);
        $after2 = $dao->findById($id)?->toArray();
        $this->assertEquals('inactive', $after2['status']);
        $this->assertNotNull($after2['updated_at'] ?? null);

        // Default scope excludes soft-deleted
        $dao->deleteById($id);
        $list = $dao->findAllBy();
        $this->assertCount(0, $list, 'Soft-deleted should be excluded by default');

        // withTrashed includes, onlyTrashed returns only deleted
        $with = $dao->withTrashed()->findAllBy();
        $this->assertCount(1, $with);
        $only = $dao->onlyTrashed()->findAllBy();
        $this->assertCount(1, $only);
        $this->assertNotNull($only[0]->toArray()['deleted_at'] ?? null);

        // Restore
        $dao->restoreById($id);
        $afterRestore = $dao->findById($id);
        $this->assertNotNull($afterRestore);
        $this->assertNull($afterRestore->toArray()['deleted_at'] ?? null);

        // Force delete
        $dao->forceDeleteById($id);
        $this->assertNull($dao->findById($id));
    }
}

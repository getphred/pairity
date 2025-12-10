<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;
use Pairity\Orm\UnitOfWork;

final class UnitOfWorkSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testDeferredUpdateAndDeleteCommit(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, name TEXT)');

        // DAO/DTO inline
        $dto = new class([]) extends AbstractDto {};
        $dtoClass = get_class($dto);
        $dao = new class($conn, $dtoClass) extends AbstractDao {
            private string $dto;
            public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey' => 'id', 'columns' => ['id'=>['cast'=>'int'],'email'=>['cast'=>'string'],'name'=>['cast'=>'string']]]; }
        };

        // Insert immediate
        $created = $dao->insert(['email' => 'u@example.com', 'name' => 'User']);
        $id = (int)($created->toArray(false)['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        // Run UoW with deferred update and delete
        UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
            $one = $dao->findById($id); // attaches to identity map
            $this->assertNotNull($one);
            // defer update
            $dao->update($id, ['name' => 'Changed']);
            // defer deleteBy criteria (will be executed after update)
            $dao->deleteBy(['id' => $id]);
            // commit done by run()
        });

        // After commit, record should be deleted
        $this->assertNull($dao->findById($id));
    }

    public function testRollbackOnExceptionClearsOps(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, name TEXT)');

        $dto = new class([]) extends AbstractDto {};
        $dtoClass = get_class($dto);
        $dao = new class($conn, $dtoClass) extends AbstractDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string'],'name'=>['cast'=>'string']]]; }
        };

        $created = $dao->insert(['email' => 'x@example.com', 'name' => 'X']);
        $id = (int)($created->toArray(false)['id'] ?? 0);

        try {
            UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
                $dao->update($id, ['name' => 'Won\'t Persist']);
                throw new \RuntimeException('boom');
            });
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            // ok
        }

        // Update should not be applied due to rollback
        $after = $dao->findById($id);
        $this->assertSame('X', $after?->toArray(false)['name'] ?? null);
    }
}

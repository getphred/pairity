<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;
use Pairity\Events\Events;
use Pairity\Orm\UnitOfWork;

final class EventSystemSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testDaoEventsForInsertUpdateDeleteAndFind(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT)');

        $Dto = new class([]) extends AbstractDto {};
        $dtoClass = get_class($Dto);
        $Dao = new class($conn, $dtoClass) extends AbstractDao {
            private string $dto; public function __construct($c,string $d){ parent::__construct($c); $this->dto=$d; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string'],'status'=>['cast'=>'string']]]; }
        };
        $dao = new $Dao($conn, $dtoClass);

        $beforeInsertData = null; $afterInsertName = null; $afterUpdateName = null; $afterDeleteAffected = null; $afterFindCount = null;

        Events::dispatcher()->clear();
        Events::dispatcher()->listen('dao.beforeInsert', function(array &$p) use (&$beforeInsertData) {
            if (($p['table'] ?? '') === 'users') {
                // mutate data
                $p['data']['status'] = 'mutated';
                $beforeInsertData = $p['data'];
            }
        });
        Events::dispatcher()->listen('dao.afterInsert', function(array &$p) use (&$afterInsertName) {
            if (($p['table'] ?? '') === 'users' && $p['dto'] instanceof AbstractDto) {
                $afterInsertName = $p['dto']->toArray(false)['name'] ?? null;
            }
        });
        Events::dispatcher()->listen('dao.afterUpdate', function(array &$p) use (&$afterUpdateName) {
            if (($p['table'] ?? '') === 'users' && $p['dto'] instanceof AbstractDto) {
                $afterUpdateName = $p['dto']->toArray(false)['name'] ?? null;
            }
        });
        Events::dispatcher()->listen('dao.afterDelete', function(array &$p) use (&$afterDeleteAffected) {
            if (($p['table'] ?? '') === 'users') { $afterDeleteAffected = (int)($p['affected'] ?? 0); }
        });
        Events::dispatcher()->listen('dao.afterFind', function(array &$p) use (&$afterFindCount) {
            if (($p['table'] ?? '') === 'users') {
                if (isset($p['dto'])) { $afterFindCount = ($p['dto'] ? 1 : 0); }
                if (isset($p['dtos'])) { $afterFindCount = is_array($p['dtos']) ? count($p['dtos']) : 0; }
            }
        });

        // Insert (beforeInsert should set status)
        $created = $dao->insert(['name' => 'Alice']);
        $arr = $created->toArray(false);
        $this->assertSame('mutated', $arr['status'] ?? null);
        $this->assertSame('Alice', $afterInsertName);

        // Update
        $id = (int)$arr['id'];
        $updated = $dao->update($id, ['name' => 'Alice2']);
        $this->assertSame('Alice2', $afterUpdateName);

        // Find
        $one = $dao->findById($id);
        $this->assertSame(1, $afterFindCount);

        // Delete
        $aff = $dao->deleteById($id);
        $this->assertSame($aff, $afterDeleteAffected);
    }

    public function testUowBeforeAfterCommitEvents(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $Dto = new class([]) extends AbstractDto {};
        $dtoClass = get_class($Dto);
        $Dao = new class($conn, $dtoClass) extends AbstractDao {
            private string $dto; public function __construct($c,string $d){ parent::__construct($c); $this->dto=$d; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };
        $dao = new $Dao($conn, $dtoClass);

        $before = 0; $after = 0;
        Events::dispatcher()->clear();
        Events::dispatcher()->listen('uow.beforeCommit', function(array &$p) use (&$before) { $before++; });
        Events::dispatcher()->listen('uow.afterCommit', function(array &$p) use (&$after) { $after++; });

        $row = $dao->insert(['name' => 'X']);
        $id = (int)($row->toArray(false)['id'] ?? 0);

        UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
            $dao->update($id, ['name' => 'Y']);
            $dao->deleteBy(['id' => $id]);
        });

        $this->assertSame(1, $before);
        $this->assertSame(1, $after);
    }
}

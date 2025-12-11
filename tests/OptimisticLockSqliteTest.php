<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;
use Pairity\Orm\OptimisticLockException;

final class OptimisticLockSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testVersionLockingIncrementsAndBlocksBulkUpdate(): void
    {
        $conn = $this->conn();
        // schema with version column
        $conn->execute('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            version INTEGER NOT NULL DEFAULT 0
        )');

        $UserDto = new class([]) extends AbstractDto {};
        $dtoClass = get_class($UserDto);

        $UserDao = new class($conn, $dtoClass) extends AbstractDao {
            private string $dto; public function __construct($c,string $dto){ parent::__construct($c); $this->dto=$dto; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array {
                return [
                    'primaryKey' => 'id',
                    'columns' => [ 'id'=>['cast'=>'int'], 'name'=>['cast'=>'string'], 'version'=>['cast'=>'int'] ],
                    'locking' => ['type' => 'version', 'column' => 'version'],
                ];
            }
        };

        $dao = new $UserDao($conn, $dtoClass);

        // Insert
        $created = $dao->insert(['name' => 'A']);
        $arr = $created->toArray(false);
        $id = (int)$arr['id'];

        // First update: should succeed and bump version to 1
        $dao->update($id, ['name' => 'A1']);
        $row = $conn->query('SELECT name, version FROM users WHERE id = :id', ['id' => $id])[0] ?? [];
        $this->assertSame('A1', (string)($row['name'] ?? ''));
        $this->assertSame(1, (int)($row['version'] ?? 0));

        // Bulk update should throw while locking enabled
        $this->expectException(OptimisticLockException::class);
        $dao->updateBy(['id' => $id], ['name' => 'A2']);
    }
}

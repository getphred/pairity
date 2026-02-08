<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\DAO;

use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\DTO\IdentityMap;
use PHPUnit\Framework\TestCase;

class OptimisticLockingTest extends TestCase
{
    public function test_it_increments_version_on_update()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $db->method('connection')->willReturn($connection);
        $db->method('getQueryGrammar')->willReturn(new SqliteGrammar());

        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'users';
            protected ?string $lockingColumn = 'version';
        };

        $dto = $this->createMock(BaseDTO::class);
        $dto->method('toArray')->willReturn(['id' => 1, 'name' => 'Updated', 'version' => 1]);

        $connection->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('AND "version" = ?'),
                ['Updated', 2, 1, 1] // name='Updated', version=2, id=1, old_version=1
            )
            ->willReturn(1);

        $dao->save($dto);
    }

    public function test_it_throws_exception_on_lock_failure()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $db->method('connection')->willReturn($connection);
        $db->method('getQueryGrammar')->willReturn(new SqliteGrammar());

        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'users';
            protected ?string $lockingColumn = 'version';
        };

        $dto = $this->createMock(BaseDTO::class);
        $dto->method('toArray')->willReturn(['id' => 1, 'name' => 'Updated', 'version' => 1]);

        $connection->method('execute')->willReturn(0);

        $this->expectException(\Pairity\Exceptions\DatabaseException::class);
        $this->expectExceptionMessage('Optimistic locking failed');

        $dao->save($dto);
    }
}

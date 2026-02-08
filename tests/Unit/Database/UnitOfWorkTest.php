<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database;

use Pairity\Database\UnitOfWork;
use Pairity\DTO\BaseDTO;
use Pairity\DAO\BaseDAO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class UnitOfWorkTest extends TestCase
{
    protected $db;
    protected $uow;

    protected function setUp(): void
    {
        $this->db = $this->createMock(DatabaseManagerInterface::class);
        $this->uow = new UnitOfWork($this->db);
    }

    public function test_it_tracks_dto_states()
    {
        $dto = $this->createMock(BaseDTO::class);
        
        $this->uow->track($dto, UnitOfWork::STATE_CLEAN);
        $this->assertEquals(UnitOfWork::STATE_CLEAN, $this->uow->getState($dto));

        $this->uow->registerDirty($dto);
        $this->assertEquals(UnitOfWork::STATE_DIRTY, $this->uow->getState($dto));

        $this->uow->registerDeleted($dto);
        $this->assertEquals(UnitOfWork::STATE_DELETED, $this->uow->getState($dto));
    }

    public function test_register_dirty_does_not_override_new()
    {
        $dto = $this->createMock(BaseDTO::class);
        $this->uow->registerNew($dto);
        $this->assertEquals(UnitOfWork::STATE_NEW, $this->uow->getState($dto));

        $this->uow->registerDirty($dto);
        $this->assertEquals(UnitOfWork::STATE_NEW, $this->uow->getState($dto));
    }

    public function test_commit_executes_dao_actions_and_uses_transactions()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');

        $this->db->method('connection')->willReturn($connection);

        $dao = $this->createMock(BaseDAO::class);
        $dao->method('getConnectionName')->willReturn('default');
        $dao->method('getPrimaryKey')->willReturn('id');

        $dtoNew = $this->createMock(BaseDTO::class);
        $dtoNew->method('getDao')->willReturn($dao);
        
        $dtoDirty = $this->createMock(BaseDTO::class);
        $dtoDirty->method('getDao')->willReturn($dao);

        $dtoDeleted = $this->createMock(BaseDTO::class);
        $dtoDeleted->method('getDao')->willReturn($dao);
        $dtoDeleted->method('toArray')->willReturn(['id' => 1]);

        $this->uow->registerNew($dtoNew);
        $this->uow->registerDirty($dtoDirty);
        $this->uow->registerDeleted($dtoDeleted);

        $dao->expects($this->exactly(2))->method('save')->with($this->callback(fn($d) => $d === $dtoNew || $d === $dtoDirty));
        $dao->expects($this->once())->method('delete')->with(1);

        $this->uow->commit();
        
        $this->assertNull($this->uow->getState($dtoNew));
    }
}

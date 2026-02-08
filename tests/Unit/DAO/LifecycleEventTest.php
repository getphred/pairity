<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\DAO;

use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Events\DispatcherInterface;
use Pairity\DTO\IdentityMap;
use PHPUnit\Framework\TestCase;

class LifecycleEventTest extends TestCase
{
    public function test_dao_fires_events()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $dispatcher = $this->createMock(DispatcherInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $db->method('getDispatcher')->willReturn($dispatcher);

        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'test';
            public function trigger(string $event, $payload) {
                return $this->fireModelEvent($event, $payload);
            }
        };

        $dto = new class extends BaseDTO {};

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->stringContains('pairity.model.saving:'), $dto, true)
            ->willReturn(true);

        $dao->trigger('saving', $dto);
    }

    public function test_dao_can_halt_via_event()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $dispatcher = $this->createMock(DispatcherInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $db->method('getDispatcher')->willReturn($dispatcher);

        // Mock DAO using generated stub logic
        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'test';
            protected string $primaryKey = 'id';
            
            public function save(BaseDTO $dto): bool
            {
                if ($this->fireModelEvent('saving', $dto) === false) {
                    return false;
                }
                return true;
            }
        };

        $dto = new class extends BaseDTO {};

        $dispatcher->method('dispatch')->willReturn(false);

        $this->assertFalse($dao->save($dto));
    }
}

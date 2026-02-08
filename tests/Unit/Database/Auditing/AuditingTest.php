<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Auditing;

use Pairity\Database\Auditing\Auditor;
use Pairity\Database\Auditing\AuditListener;
use Pairity\Events\Dispatcher;
use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\DTO\IdentityMap;
use PHPUnit\Framework\TestCase;

class AuditingTest extends TestCase
{
    public function test_it_records_audit_on_events()
    {
        $auditor = $this->createMock(Auditor::class);
        $listener = new AuditListener($auditor);
        $dispatcher = new Dispatcher();
        
        $listener->subscribe($dispatcher);

        $db = $this->createMock(DatabaseManagerInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'users';
            protected bool $auditable = true;
            public function getOption(string $key, mixed $default = null): mixed {
                if ($key === 'auditable') return true;
                return parent::getOption($key, $default);
            }
        };

        $dto = new class extends BaseDTO {};
        $dto->setDao($dao);

        $auditor->expects($this->once())
            ->method('getChanges')
            ->willReturn(['old' => [], 'new' => ['name' => 'John']]);

        $auditor->expects($this->once())
            ->method('record')
            ->with($dto, 'created', [], ['name' => 'John']);

        $dispatcher->dispatch('pairity.model.created: ' . get_class($dto), $dto);
    }
}

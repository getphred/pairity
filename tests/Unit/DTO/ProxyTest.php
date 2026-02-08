<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\DTO;

use App\Models\DTO\UsersDTO;
use App\Models\DAO\UsersDAO;
use Pairity\DTO\ProxyFactory;
use Pairity\DTO\ProxyInterface;
use Pairity\DTO\IdentityMap;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    public function test_it_can_create_proxy()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $identityMap = new IdentityMap();
        $dao = new UsersDAO($db, $identityMap);
        
        $factory = new ProxyFactory();
        $proxy = $factory->create(UsersDTO::class, $dao, 123);

        $this->assertInstanceOf(UsersDTO::class, $proxy);
        $this->assertInstanceOf(ProxyInterface::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());
        $this->assertEquals(123, $proxy->getId());
    }

    public function test_it_triggers_load_on_access()
    {
        $conn = $this->createMock(ConnectionInterface::class);
        $conn->method('select')->willReturn([['id' => 123, 'email' => 'loaded@example.com']]);
        
        $db = $this->createMock(DatabaseManagerInterface::class);
        $db->method('connection')->willReturn($conn);
        
        $identityMap = new IdentityMap();
        $dao = new UsersDAO($db, $identityMap);
        
        $factory = new ProxyFactory();
        $proxy = $factory->create(UsersDTO::class, $dao, 123);

        // Accessing email should trigger load
        $email = $proxy->getEmail();

        $this->assertTrue($proxy->__isInitialized());
        $this->assertEquals('loaded@example.com', $email);
    }
}

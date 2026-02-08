<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query\Scopes;

use PHPUnit\Framework\TestCase;
use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Scopes\TenantScope;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\DAO\BaseDAO;
use Pairity\DTO\IdentityMap;

class TenantScopeTest extends TestCase
{
    protected function setUp(): void
    {
        TenantScope::setTenantId(null);
        TenantScope::setTenantColumn('tenant_id');
    }

    public function test_it_applies_tenant_scope_to_builder()
    {
        TenantScope::setTenantId(1);
        
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        
        $builder = new Builder($db, $connection, $grammar);
        
        $dao = $this->createMock(BaseDAO::class);
        $dao->method('getOption')->with('tenancy', false)->willReturn(true);
        
        $builder->setModel('TestDTO', $dao);
        $builder->from('users');
        
        $sql = $builder->toSql();
        $this->assertStringContainsString('where "tenant_id" = ?', $sql);
        $this->assertEquals([1], $builder->getBindings());
    }

    public function test_it_can_disable_tenancy()
    {
        TenantScope::setTenantId(1);
        
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        
        $builder = new Builder($db, $connection, $grammar);
        
        $dao = $this->createMock(BaseDAO::class);
        $dao->method('getOption')->with('tenancy', false)->willReturn(true);
        
        $builder->setModel('TestDTO', $dao);
        $builder->from('users')->withoutTenancy();
        
        $sql = $builder->toSql();
        $this->assertStringNotContainsString('where "tenant_id" = ?', $sql);
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_it_injects_tenant_id_on_insert()
    {
        TenantScope::setTenantId(99);
        
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $identityMap = $this->createMock(IdentityMap::class);
        
        $driver = $this->createMock(\Pairity\Contracts\Database\DriverInterface::class);
        $driver->method('getName')->willReturn('sqlite');
        $connection->method('getDriver')->willReturn($driver);
        $db->method('getQueryGrammar')->willReturn(new SqliteGrammar());
        $db->method('connection')->willReturn($connection);

        $dao = new class($db, $identityMap) extends BaseDAO {
            protected string $table = 'users';
            protected array $options = ['tenancy' => true];
            
            public function testInsert(array $data) {
                return $this->insert($data);
            }
        };

        $connection->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($sql) {
                return strpos($sql, '"tenant_id"') !== false;
            }), $this->callback(function($bindings) {
                return in_array(99, $bindings);
            }))
            ->willReturn(1);

        $dao->testInsert(['name' => 'John']);
    }
}

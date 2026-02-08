<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class AdvancedQueryTest extends TestCase
{
    public function test_it_compiles_unions()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        
        $builder = new Builder($db, $connection, $grammar);
        $builder->from('users')->select('id', 'name');
        
        $unionBuilder = new Builder($db, $connection, $grammar);
        $unionBuilder->from('guests')->select('id', 'name');
        
        $builder->union($unionBuilder);
        
        $this->assertEquals('(select "id", "name" from "users") union (select "id", "name" from "guests")', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function test_it_compiles_unions_with_bindings()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        
        $builder = new Builder($db, $connection, $grammar);
        $builder->from('users')->where('id', 1);
        
        $unionBuilder = new Builder($db, $connection, $grammar);
        $unionBuilder->from('users')->where('id', 2);
        
        $builder->union($unionBuilder);
        
        $this->assertEquals('(select * from "users" where "id" = ?) union (select * from "users" where "id" = ?)', $builder->toSql());
        $this->assertEquals([1, 2], $builder->getBindings());
    }

    public function test_it_caches_query_results()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $grammar = new SqliteGrammar();
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->with(CacheInterface::class)->willReturn($cache);
        
        $builder = new Builder($db, $connection, $grammar);
        $builder->from('users')->remember(60);
        
        $cache->method('has')->willReturn(true);
        $cache->method('get')->willReturn(['cached_result']);
        
        $connection->expects($this->never())->method('select');
        
        $results = $builder->get();
        
        $this->assertEquals(['cached_result'], $results);
    }
}

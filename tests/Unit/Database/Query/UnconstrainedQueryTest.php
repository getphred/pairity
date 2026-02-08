<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

class UnconstrainedQueryTest extends TestCase
{
    protected function getBuilder(array $config = [])
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $translator = new \Pairity\Translation\Translator(dirname(__DIR__, 4) . '/src/Translations');
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->with(\Pairity\Contracts\Translation\TranslatorInterface::class)->willReturn($translator);
        
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn($config);
        $grammar = new SqliteGrammar();
        return new Builder($db, $connection, $grammar);
    }

    public function test_it_throws_exception_on_unconstrained_update()
    {
        $builder = $this->getBuilder(['allow_unconstrained_queries' => false]);
        $builder->from('users');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unconstrained update queries are disabled by configuration.');

        $builder->update(['name' => 'John']);
    }

    public function test_it_throws_exception_on_unconstrained_delete()
    {
        $builder = $this->getBuilder(['allow_unconstrained_queries' => false]);
        $builder->from('users');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unconstrained delete queries are disabled by configuration.');

        $builder->delete();
    }

    public function test_it_allows_constrained_update()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => false]);
        $connection->expects($this->once())
            ->method('execute')
            ->with('update "users" set "name" = ? where "id" = ?', ['John', 1])
            ->willReturn(1);

        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $db->method('getContainer')->willReturn($container);

        $builder = new Builder($db, $connection, new SqliteGrammar());
        $builder->from('users')->where('id', 1);

        $affected = $builder->update(['name' => 'John']);
        $this->assertEquals(1, $affected);
    }

    public function test_it_allows_constrained_delete()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => false]);
        $connection->expects($this->once())
            ->method('execute')
            ->with('delete from "users" where "id" = ?', [1])
            ->willReturn(1);

        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $db->method('getContainer')->willReturn($container);

        $builder = new Builder($db, $connection, new SqliteGrammar());
        $builder->from('users')->where('id', 1);

        $affected = $builder->delete();
        $this->assertEquals(1, $affected);
    }

    public function test_it_allows_unconstrained_update_if_configured()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => true]);
        $connection->expects($this->once())
            ->method('execute')
            ->with('update "users" set "name" = ?', ['John'])
            ->willReturn(5);

        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $db->method('getContainer')->willReturn($container);

        $builder = new Builder($db, $connection, new SqliteGrammar());
        $builder->from('users');

        $affected = $builder->update(['name' => 'John']);
        $this->assertEquals(5, $affected);
    }

    public function test_it_allows_unconstrained_delete_if_configured()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => true]);
        $connection->expects($this->once())
            ->method('execute')
            ->with('delete from "users"', [])
            ->willReturn(5);

        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $db->method('getContainer')->willReturn($container);

        $builder = new Builder($db, $connection, new SqliteGrammar());
        $builder->from('users');

        $affected = $builder->delete();
        $this->assertEquals(5, $affected);
    }

    public function test_it_throws_exception_on_unconstrained_update_through_dao()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => false]);
        $grammar = new SqliteGrammar();
        $db = $this->createMock(\Pairity\Contracts\Database\DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $translator = new \Pairity\Translation\Translator(dirname(__DIR__, 4) . '/src/Translations');
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->with(\Pairity\Contracts\Translation\TranslatorInterface::class)->willReturn($translator);
        
        $db->method('connection')->willReturn($connection);
        $db->method('getQueryGrammar')->willReturn($grammar);
        $identityMap = new \Pairity\DTO\IdentityMap();

        $dao = new \App\Models\DAO\UsersDAO($db, $identityMap);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unconstrained update queries are disabled by configuration.');

        $dao->massUpdate(['email' => 'hacked@example.com']);
    }

    public function test_it_throws_exception_on_unconstrained_delete_through_dao()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getConfig')->willReturn(['allow_unconstrained_queries' => false]);
        $grammar = new SqliteGrammar();
        $db = $this->createMock(\Pairity\Contracts\Database\DatabaseManagerInterface::class);
        $container = $this->createMock(\Pairity\Contracts\Container\ContainerInterface::class);
        $translator = new \Pairity\Translation\Translator(dirname(__DIR__, 4) . '/src/Translations');
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->with(\Pairity\Contracts\Translation\TranslatorInterface::class)->willReturn($translator);
        
        $db->method('connection')->willReturn($connection);
        $db->method('getQueryGrammar')->willReturn($grammar);
        $identityMap = new \Pairity\DTO\IdentityMap();

        $dao = new \App\Models\DAO\UsersDAO($db, $identityMap);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unconstrained delete queries are disabled by configuration.');

        $dao->massDelete();
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use PHPUnit\Framework\TestCase;

class UpsertTest extends TestCase
{
    public function test_sqlite_upsert_generation()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        $builder = new Builder($db, $connection, $grammar);

        $values = [
            ['email' => 'test@example.com', 'name' => 'Test'],
            ['email' => 'foo@bar.com', 'name' => 'Foo']
        ];
        
        $sql = $grammar->compileUpsert($builder->from('users'), $values, ['email'], ['name']);
        
        $expected = 'insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "name" = excluded."name"';
        $this->assertEquals($expected, $sql);
    }

    public function test_upsert_bindings()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        $builder = new Builder($db, $connection, $grammar);

        $values = [
            ['email' => 'test@example.com', 'name' => 'Test'],
            ['email' => 'foo@bar.com', 'name' => 'Foo']
        ];
        
        $connection->expects($this->once())
            ->method('execute')
            ->with($this->anything(), ['test@example.com', 'Test', 'foo@bar.com', 'Foo']);

        $builder->from('users')->upsert($values, ['email'], ['name']);
    }
}

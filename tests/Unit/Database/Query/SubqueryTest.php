<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use PHPUnit\Framework\TestCase;

class SubqueryTest extends TestCase
{
    protected function getBuilder()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        return new Builder($db, $connection, $grammar);
    }

    public function test_it_handles_subquery_in_from()
    {
        $builder = $this->getBuilder();
        $builder->from(function ($query) {
            $query->select('id')->from('users')->where('active', true);
        }, 'active_users');

        $this->assertEquals('select * from (select "id" from "users" where "active" = ?) as "active_users"', $builder->toSql());
        $this->assertEquals([true], $builder->getBindings());
    }

    public function test_it_handles_builder_instance_in_from()
    {
        $subQuery = $this->getBuilder()->select('id')->from('users')->where('id', '>', 10);
        
        $builder = $this->getBuilder();
        $builder->from($subQuery, 'filtered_users');

        $this->assertEquals('select * from (select "id" from "users" where "id" > ?) as "filtered_users"', $builder->toSql());
        $this->assertEquals([10], $builder->getBindings());
    }

    public function test_it_handles_where_in_subquery()
    {
        $builder = $this->getBuilder();
        $builder->from('posts')->whereIn('user_id', function ($query) {
            $query->select('id')->from('users')->where('banned', false);
        });

        $this->assertEquals('select * from "posts" where "user_id" in (select "id" from "users" where "banned" = ?)', $builder->toSql());
        $this->assertEquals([false], $builder->getBindings());
    }

    public function test_it_handles_complex_nested_bindings()
    {
        $builder = $this->getBuilder();
        $builder->from(function ($query) {
            $query->select('user_id')->from('orders')->where('amount', '>', 100);
        }, 'big_orders')
        ->whereIn('user_id', function ($query) {
            $query->select('id')->from('users')->where('country', 'US');
        })
        ->where('status', 'shipped');

        $expectedSql = 'select * from (select "user_id" from "orders" where "amount" > ?) as "big_orders" where "user_id" in (select "id" from "users" where "country" = ?) and "status" = ?';
        
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([100, 'US', 'shipped'], $builder->getBindings());
    }

    public function test_it_handles_where_not_in_subquery()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->whereNotIn('id', function ($query) {
            $query->select('user_id')->from('banned_users');
        });

        $this->assertEquals('select * from "users" where "id" not in (select "user_id" from "banned_users")', $builder->toSql());
    }

    public function test_it_handles_where_in_array()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->whereIn('id', [1, 2, 3]);

        $this->assertEquals('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([1, 2, 3], $builder->getBindings());
    }

    public function test_it_handles_empty_where_in_array()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->whereIn('id', []);

        $this->assertEquals('select * from "users" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }
}

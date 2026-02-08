<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Database\Query\QueryResult;
use Pairity\Database\Query\Expression;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use PHPUnit\Framework\TestCase;

class AdvancedSubqueryTest extends TestCase
{
    protected function getBuilder()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        return new Builder($db, $connection, $grammar);
    }

    public function test_query_result_magic_access()
    {
        $result = new QueryResult([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'is_active' => true
        ]);

        $this->assertEquals('test@example.com', $result->email);
        $this->assertEquals('test@example.com', $result->getEmail());
        $this->assertEquals('John', $result->first_name);
        $this->assertEquals('John', $result->getFirstName());
        $this->assertTrue($result->is_active);
        $this->assertTrue($result->getIsActive());
        $this->assertNull($result->non_existent);
        $this->assertNull($result->getNonExistent());
    }

    public function test_subquery_joins()
    {
        $builder = $this->getBuilder();
        
        $builder->from('users')
            ->joinSub(function ($query) {
                $query->select([
                    'user_id', 
                    'last_post' => new Expression('MAX("created_at")')
                ])
                ->from('posts')
                ->groupBy('user_id');
            }, 'latest_posts', 'users.id', '=', 'latest_posts.user_id');

        $expectedSql = 'select * from "users" inner join (select "user_id", MAX("created_at") as "last_post" from "posts" group by "user_id") as "latest_posts" on "users.id" = "latest_posts.user_id"';
        
        $this->assertEquals($expectedSql, $builder->toSql());
    }

    public function test_where_exists()
    {
        $builder = $this->getBuilder();

        $builder->from('users')
            ->whereExists(function ($query) {
                $query->select('*')
                    ->from('orders')
                    ->where('orders.user_id', 'users.id');
            });

        $expectedSql = 'select * from "users" where exists (select * from "orders" where "orders.user_id" = ?)';
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals(['users.id'], $builder->getBindings());
    }

    public function test_subquery_selects()
    {
        $builder = $this->getBuilder();

        $builder->from('users')
            ->select([
                'id',
                'email',
                'last_order_date' => function ($query) {
                    $query->select(new Expression('MAX("created_at")'))
                        ->from('orders')
                        ->where('user_id', 1); // Sample binding
                }
            ]);

        $expectedSql = 'select "id", "email", (select MAX("created_at") from "orders" where "user_id" = ?) as "last_order_date" from "users"';
        
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function test_complex_nested_bindings_order()
    {
        $builder = $this->getBuilder();

        // Bindings in SELECT, JOIN, and WHERE
        $builder->select([
            'name',
            'order_count' => function($q) { $q->from('orders')->where('status', 'shipped'); } // Binding 1
        ])
        ->from('users')
        ->join(function($q) {
            $q->from('profiles')->where('type', 'pro'); // Binding 2
        }, 'p', 'users.id', '=', 'p.user_id')
        ->where('active', 1) // Binding 3
        ->whereExists(function($q) {
            $q->from('logs')->where('level', 'error'); // Binding 4
        });

        $this->assertEquals(['shipped', 'pro', 1, 'error'], $builder->getBindings());
    }
}

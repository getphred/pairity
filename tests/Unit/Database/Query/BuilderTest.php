<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\MySqlGrammar;
use Pairity\Database\Query\Grammars\PostgresGrammar;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Database\Query\Expression;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    protected function getBuilder($grammarClass = SqliteGrammar::class)
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new $grammarClass();
        return new Builder($db, $connection, $grammar);
    }

    public function test_it_compiles_basic_select()
    {
        $builder = $this->getBuilder()->from('users')->select('id', 'email');
        $this->assertEquals('select "id", "email" from "users"', $builder->toSql());
    }

    public function test_it_compiles_where_clauses()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1)->where('active', true);
        $this->assertEquals('select * from "users" where "id" = ? and "active" = ?', $builder->toSql());
        $this->assertEquals([1, true], $builder->getBindings());
    }

    public function test_it_compiles_or_where()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1)->orWhere('email', 'test@example.com');
        $this->assertEquals('select * from "users" where "id" = ? or "email" = ?', $builder->toSql());
    }

    public function test_it_compiles_joins()
    {
        $builder = $this->getBuilder()->from('users')
            ->join('posts', 'users.id', '=', 'posts.user_id');
        $this->assertEquals('select * from "users" inner join "posts" on "users.id" = "posts.user_id"', $builder->toSql());
    }

    public function test_it_compiles_limit_and_offset()
    {
        $builder = $this->getBuilder()->from('users')->limit(10)->offset(5);
        $this->assertEquals('select * from "users" limit 10 offset 5', $builder->toSql());
    }

    public function test_mysql_grammar_uses_backticks()
    {
        $builder = $this->getBuilder(MySqlGrammar::class)->from('users')->select('id');
        $this->assertEquals('select `id` from `users`', $builder->toSql());
    }

    public function test_sql_method_returns_array()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1);
        $result = $builder->sql();
        
        $this->assertIsArray($result);
        $this->assertEquals('select * from "users" where "id" = ?', $result['sql']);
        $this->assertEquals([1], $result['bindings']);
    }

    public function test_it_handles_raw_expressions()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->select(new Expression('COUNT(*) as user_count'));
        
        $this->assertEquals('select COUNT(*) as user_count from "users"', $builder->toSql());
    }

    public function test_it_handles_scopes()
    {
        $dao = new class {
            public function scopeActive($builder, $value = true) {
                return $builder->where('active', $value);
            }
            public function getOption($key, $default = null) {
                return $default;
            }
        };

        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $builder = new Builder($db, $connection, new SqliteGrammar());
        $builder->setModel('TestDTO', $dao);

        $builder->active(false);

        $this->assertEquals('select * where "active" = ?', $builder->toSql());
        $this->assertEquals([false], $builder->getBindings());
    }
}

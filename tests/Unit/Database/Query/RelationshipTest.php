<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Query;

use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Grammars\SqliteGrammar;
use Pairity\Database\Query\Relations\HasMany;
use Pairity\Database\Query\Relations\BelongsTo;
use Pairity\Database\Query\Relations\HasOne;
use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\DTO\BaseDTO;
use Pairity\DAO\BaseDAO;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use PHPUnit\Framework\TestCase;

class RelationshipTest extends TestCase
{
    protected function getBuilder()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $grammar = new SqliteGrammar();
        return new Builder($db, $connection, $grammar);
    }

    public function test_has_many_relation_query_construction()
    {
        $parent = new class(['id' => 1]) extends BaseDTO {
            public $id = 1;
        };
        
        $relatedDao = $this->createMock(BaseDAO::class);
        $relatedDao->method('getTable')->willReturn('posts');
        $relatedDao->method('getDtoClass')->willReturn('PostDTO');

        $query = $this->getBuilder();
        $relation = new HasMany($query, $parent, $relatedDao, 'user_id', 'id');

        $this->assertEquals('select * from "posts" where "user_id" = ?', $relation->toSql());
        $this->assertEquals([1], $relation->getBindings());
    }

    public function test_belongs_to_relation_query_construction()
    {
        $parent = new class(['user_id' => 10]) extends BaseDTO {
            public $user_id = 10;
        };
        
        $relatedDao = $this->createMock(BaseDAO::class);
        $relatedDao->method('getTable')->willReturn('users');
        $relatedDao->method('getDtoClass')->willReturn('UserDTO');

        $query = $this->getBuilder();
        $relation = new BelongsTo($query, $parent, $relatedDao, 'user_id', 'id');

        $this->assertEquals('select * from "users" where "id" = ?', $relation->toSql());
        $this->assertEquals([10], $relation->getBindings());
    }

    public function test_eager_constraints_on_has_many()
    {
        $parent1 = new class(['id' => 1]) extends BaseDTO { public $id = 1; };
        $parent2 = new class(['id' => 2]) extends BaseDTO { public $id = 2; };
        
        $relatedDao = $this->createMock(BaseDAO::class);
        $relatedDao->method('getTable')->willReturn('posts');

        $query = $this->getBuilder();
        $relation = new HasMany($query, $parent1, $relatedDao, 'user_id', 'id');
        
        // Simulating EagerLoader behavior: reset wheres before adding eager constraints
        $relation->wheres = [];
        $relation->setBindings([], 'where');
        $relation->addEagerConstraints([$parent1, $parent2]);

        $this->assertEquals('select * from "posts" where "user_id" in (?, ?)', $relation->toSql());
        $this->assertEquals([1, 2], $relation->getBindings());
    }
}

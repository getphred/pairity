<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class RelationsNestedConstraintsSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testNestedEagerAndPerRelationFieldsConstraint(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $conn->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
        $conn->execute('CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, body TEXT)');

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $PostDto = new class([]) extends AbstractDto {};
        $CommentDto = new class([]) extends AbstractDto {};
        $uClass = get_class($UserDto); $pClass = get_class($PostDto); $cClass = get_class($CommentDto);

        // DAOs (constructors accept only connection; FQCNs via static props)
        $CommentDao = new class($conn) extends AbstractDao {
            public static string $dto;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'comments'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'post_id'=>['cast'=>'int'],'body'=>['cast'=>'string']]]; }
        };

        $commentDaoClass = get_class($CommentDao);
        $commentDaoClass::$dto = $cClass;

        $PostDao = new class($conn) extends AbstractDao {
            public static string $dto; public static string $commentDaoClass;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function relations(): array {
                return [
                    'comments' => [
                        'type' => 'hasMany',
                        'dao'  => self::$commentDaoClass,
                        'foreignKey' => 'post_id',
                        'localKey'   => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $postDaoClass = get_class($PostDao);
        $postDaoClass::$dto = $pClass;
        $postDaoClass::$commentDaoClass = $commentDaoClass;

        $UserDao = new class($conn) extends AbstractDao {
            public static string $dto; public static string $postDaoClass;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function relations(): array {
                return [
                    'posts' => [
                        'type' => 'hasMany',
                        'dao'  => self::$postDaoClass,
                        'foreignKey' => 'user_id',
                        'localKey'   => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $userDaoClass = get_class($UserDao);
        $userDaoClass::$dto = $uClass;
        $userDaoClass::$postDaoClass = $postDaoClass;

        $commentDao = new $commentDaoClass($conn);
        $postDao = new $postDaoClass($conn);
        $userDao = new $userDaoClass($conn);

        // seed
        $u = $userDao->insert(['name' => 'Alice']);
        $uid = (int)($u->toArray(false)['id'] ?? 0);
        $p1 = $postDao->insert(['user_id' => $uid, 'title' => 'P1']);
        $p2 = $postDao->insert(['user_id' => $uid, 'title' => 'P2']);
        $pid1 = (int)($p1->toArray(false)['id'] ?? 0); $pid2 = (int)($p2->toArray(false)['id'] ?? 0);
        $commentDao->insert(['post_id' => $pid1, 'body' => 'c1']);
        $commentDao->insert(['post_id' => $pid1, 'body' => 'c2']);
        $commentDao->insert(['post_id' => $pid2, 'body' => 'c3']);

        // nested eager with per-relation fields constraint (SQL supports fields projection)
        $users = $userDao
            ->fields(
                'id', 'name',
                'posts.id', 'posts.user_id', 'posts.title',
                'posts.comments.id', 'posts.comments.post_id', 'posts.comments.body'
            )
            ->with(['posts', 'posts.comments'])
            ->findAllBy(['id' => $uid]);

        $this->assertNotEmpty($users);
        $user = $users[0];
        $posts = $user->toArray(false)['posts'] ?? [];
        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
        // ensure projection respected on posts (at minimum title is present)
        $this->assertArrayHasKey('title', $posts[0]->toArray(false));
        // Note: FK like user_id may be included to support grouping during eager load.
        // nested comments should exist
        $cm = $posts[0]->toArray(false)['comments'] ?? [];
        $this->assertIsArray($cm);
        $this->assertNotEmpty($cm);
    }
}

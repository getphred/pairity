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

        // DAOs
        $CommentDao = new class($conn, $cClass) extends AbstractDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            public function getTable(): string { return 'comments'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'post_id'=>['cast'=>'int'],'body'=>['cast'=>'string']]]; }
        };

        $PostDao = new class($conn, $pClass, get_class($CommentDao)) extends AbstractDao {
            private string $dto; private string $commentDaoClass;
            public function __construct($c, string $dto, string $cd) { parent::__construct($c); $this->dto = $dto; $this->commentDaoClass = $cd; }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array {
                return [
                    'comments' => [
                        'type' => 'hasMany',
                        'dao'  => $this->commentDaoClass,
                        'foreignKey' => 'post_id',
                        'localKey'   => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $UserDao = new class($conn, $uClass, get_class($PostDao)) extends AbstractDao {
            private string $dto; private string $postDaoClass;
            public function __construct($c, string $dto, string $pd) { parent::__construct($c); $this->dto = $dto; $this->postDaoClass = $pd; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array {
                return [
                    'posts' => [
                        'type' => 'hasMany',
                        'dao'  => $this->postDaoClass,
                        'foreignKey' => 'user_id',
                        'localKey'   => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $commentDao = new $CommentDao($conn, $cClass);
        $postDao = new $PostDao($conn, $pClass, get_class($commentDao));
        $userDao = new $UserDao($conn, $uClass, get_class($postDao));

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
            ->with([
                'posts' => function (AbstractDao $dao) { $dao->fields('id', 'title'); },
                'posts.comments' // nested
            ])
            ->findAllBy(['id' => $uid]);

        $this->assertNotEmpty($users);
        $user = $users[0];
        $posts = $user->toArray(false)['posts'] ?? [];
        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
        // ensure projection respected on posts (no user_id expected)
        $this->assertArrayHasKey('title', $posts[0]->toArray(false));
        $this->assertArrayNotHasKey('user_id', $posts[0]->toArray(false));
        // nested comments should exist
        $cm = $posts[0]->toArray(false)['comments'] ?? [];
        $this->assertIsArray($cm);
        $this->assertNotEmpty($cm);
    }
}

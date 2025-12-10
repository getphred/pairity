<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;
use Pairity\Orm\UnitOfWork;

final class UnitOfWorkCascadeSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testDeleteByIdCascadesToChildren(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $conn->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');

        // DTOs
        $userDto = new class([]) extends AbstractDto {};
        $postDto = new class([]) extends AbstractDto {};
        $userDtoClass = get_class($userDto);
        $postDtoClass = get_class($postDto);

        // DAOs with hasMany relation and cascadeDelete=true
        $UserDao = new class($conn, $userDtoClass, $postDtoClass) extends AbstractDao {
            private string $userDto; private string $postDto;
            public function __construct($c, string $u, string $p) { parent::__construct($c); $this->userDto = $u; $this->postDto = $p; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->userDto; }
            protected function relations(): array {
                return [
                    'posts' => [
                        'type' => 'hasMany',
                        'dao'  => get_class(new class($this->getConnection(), $this->postDto) extends AbstractDao {
                            private string $dto; public function __construct($c, string $d) { parent::__construct($c); $this->dto = $d; }
                            public function getTable(): string { return 'posts'; }
                            protected function dtoClass(): string { return $this->dto; }
                        }),
                        'foreignKey' => 'user_id',
                        'localKey'   => 'id',
                        'cascadeDelete' => true,
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey' => 'id', 'columns' => ['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
        };

        $PostDao = new class($conn, $postDtoClass) extends AbstractDao {
            private string $dto; public function __construct($c, string $d) { parent::__construct($c); $this->dto = $d; }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $userDao = new $UserDao($conn, $userDtoClass, $postDtoClass);
        $postDao = new $PostDao($conn, $postDtoClass);

        // seed
        $u = $userDao->insert(['email' => 'c@example.com']);
        $uid = (int)($u->toArray(false)['id'] ?? 0);
        $postDao->insert(['user_id' => $uid, 'title' => 'A']);
        $postDao->insert(['user_id' => $uid, 'title' => 'B']);

        // UoW: delete user; expect posts to be deleted first via cascade
        UnitOfWork::run(function() use ($userDao, $uid) {
            $userDao->deleteById($uid);
        });

        // verify posts gone and user gone
        $remainingPosts = $postDao->findAllBy(['user_id' => $uid]);
        $this->assertCount(0, $remainingPosts, 'Child posts should be deleted via cascade');
        $this->assertNull($userDao->findById($uid));
    }
}

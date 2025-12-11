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

        // DAOs with hasMany relation and cascadeDelete=true (constructors accept only connection)
        $UserDao = new class($conn) extends AbstractDao {
            public static string $userDto; public static string $postDaoClass;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return self::$userDto; }
            protected function relations(): array {
                return [
                    'posts' => [
                        'type' => 'hasMany',
                        'dao'  => self::$postDaoClass,
                        'foreignKey' => 'user_id',
                        'localKey'   => 'id',
                        'cascadeDelete' => true,
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey' => 'id', 'columns' => ['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
        };

        $PostDao = new class($conn) extends AbstractDao {
            public static string $dto;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $postDaoClass = get_class($PostDao);
        $postDaoClass::$dto = $postDtoClass;

        $userDaoClass = get_class($UserDao);
        $userDaoClass::$userDto = $userDtoClass;
        $userDaoClass::$postDaoClass = $postDaoClass;

        $userDao = new $userDaoClass($conn);
        $postDao = new $postDaoClass($conn);

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

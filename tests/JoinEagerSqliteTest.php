<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class JoinEagerSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testHasManyJoinEagerWithProjectionAndSoftDeleteScope(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $conn->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, deleted_at TEXT NULL)');

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $PostDto = new class([]) extends AbstractDto {};
        $uClass = get_class($UserDto); $pClass = get_class($PostDto);

        // DAOs
        $PostDao = new class($conn, $pClass) extends AbstractDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return [
                'primaryKey' => 'id',
                'columns' => [ 'id'=>['cast'=>'int'], 'user_id'=>['cast'=>'int'], 'title'=>['cast'=>'string'], 'deleted_at'=>['cast'=>'datetime'] ],
                'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
            ]; }
        };

        $UserDao = new class($conn, $uClass, get_class($PostDao)) extends AbstractDao {
            private string $dto; private string $postDaoClass; public function __construct($c,string $dto,string $p){ parent::__construct($c); $this->dto=$dto; $this->postDaoClass=$p; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [
                'posts' => [ 'type' => 'hasMany', 'dao' => $this->postDaoClass, 'foreignKey' => 'user_id', 'localKey' => 'id' ],
            ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $postDao = new $PostDao($conn, $pClass);
        $userDao = new $UserDao($conn, $uClass, get_class($postDao));

        // seed
        $u1 = $userDao->insert(['name' => 'Alice']);
        $u2 = $userDao->insert(['name' => 'Bob']);
        $uid1 = (int)$u1->toArray(false)['id'];
        $uid2 = (int)$u2->toArray(false)['id'];
        $postDao->insert(['user_id' => $uid1, 'title' => 'P1']);
        $postDao->insert(['user_id' => $uid1, 'title' => 'P2']);
        $postDao->insert(['user_id' => $uid2, 'title' => 'Hidden', 'deleted_at' => gmdate('Y-m-d H:i:s')]); // soft-deleted

        // Batched (subquery) for baseline
        $baseline = $userDao->fields('id','name','posts.title')->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $baseline);
        $alice = $baseline[0]->toArray(false);
        $this->assertIsArray($alice['posts'] ?? null);
        $this->assertCount(2, $alice['posts']);

        // Join-based eager (opt-in). Requires relation field projection.
        $joined = $userDao->fields('id','name','posts.title')->useJoinEager()->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $joined);
        $aliceJ = $joined[0]->toArray(false);
        $this->assertIsArray($aliceJ['posts'] ?? null);
        $this->assertCount(2, $aliceJ['posts']);

        // Ensure soft-deleted child was filtered out via ON condition
        foreach ($joined as $u) {
            $posts = $u->toArray(false)['posts'] ?? [];
            foreach ($posts as $p) {
                $this->assertNotSame('Hidden', $p->toArray(false)['title'] ?? null);
            }
        }
    }

    public function testBelongsToJoinEagerSingleLevel(): void
    {
        $conn = $this->conn();
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $conn->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');

        $UserDto = new class([]) extends AbstractDto {};
        $PostDto = new class([]) extends AbstractDto {};
        $uClass = get_class($UserDto); $pClass = get_class($PostDto);

        $UserDao = new class($conn, $uClass) extends AbstractDao {
            private string $dto; public function __construct($c,string $dto){ parent::__construct($c); $this->dto=$dto; }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };
        $PostDao = new class($conn, $pClass, get_class($UserDao)) extends AbstractDao {
            private string $dto; private string $userDaoClass; public function __construct($c,string $dto,string $u){ parent::__construct($c); $this->dto=$dto; $this->userDaoClass=$u; }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [
                'user' => [ 'type' => 'belongsTo', 'dao' => $this->userDaoClass, 'foreignKey' => 'user_id', 'otherKey' => 'id' ],
            ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $userDao = new $UserDao($conn, $uClass);
        $postDao = new $PostDao($conn, $pClass, get_class($userDao));

        $u = $userDao->insert(['name' => 'Alice']);
        $uid = (int)$u->toArray(false)['id'];
        $p = $postDao->insert(['user_id' => $uid, 'title' => 'Hello']);

        $rows = $postDao->fields('id','title','user.name')->useJoinEager()->with(['user'])->findAllBy([]);
        $this->assertNotEmpty($rows);
        $arr = $rows[0]->toArray(false);
        $this->assertSame('Hello', $arr['title']);
        $this->assertSame('Alice', $arr['user']->toArray(false)['name'] ?? null);
    }
}

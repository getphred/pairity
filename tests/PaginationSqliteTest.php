<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class PaginationSqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testPaginateAndSimplePaginateWithScopesAndRelations(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, status TEXT)');
        $conn->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $PostDto = new class([]) extends AbstractDto {};
        $uClass = get_class($UserDto); $pClass = get_class($PostDto);

        // DAOs
        $PostDao = new class($conn, $pClass) extends AbstractDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            public function getTable(): string { return 'posts'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };

        $UserDao = new class($conn, $uClass, get_class($PostDao)) extends AbstractDao {
            private string $dto; private string $postDaoClass; public function __construct($c,string $dto,string $p){ parent::__construct($c); $this->dto=$dto; $this->postDaoClass=$p; }
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
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string'],'status'=>['cast'=>'string']]]; }
        };

        $postDao = new $PostDao($conn, $pClass);
        $userDao = new $UserDao($conn, $uClass, get_class($postDao));

        // seed 35 users (20 active, 15 inactive)
        for ($i=1; $i<=35; $i++) {
            $status = $i <= 20 ? 'active' : 'inactive';
            $u = $userDao->insert(['email' => "u{$i}@example.com", 'status' => $status]);
            $uid = (int)($u->toArray(false)['id'] ?? 0);
            if ($i % 5 === 0) {
                $postDao->insert(['user_id' => $uid, 'title' => 'P'.$i]);
            }
        }

        // paginate page 2 of size 10
        $page = $userDao->paginate(2, 10, []);
        $this->assertSame(35, $page['total']);
        $this->assertSame(10, count($page['data']));
        $this->assertSame(4, $page['lastPage']);
        $this->assertSame(2, $page['currentPage']);

        // simplePaginate last page should have nextPage null
        $simple = $userDao->simplePaginate(4, 10, []);
        $this->assertNull($simple['nextPage']);
        $this->assertSame(10, $simple['perPage']);

        // fields() projection + with() eager on paginated results
        $with = $userDao->fields('id', 'email', 'posts.title')->with(['posts'])->paginate(1, 10);
        $this->assertNotEmpty($with['data']);
        $first = $with['data'][0]->toArray(false);
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('email', $first);
        $this->assertArrayHasKey('posts', $first);

        // scopes: named scope to filter active users only
        $userDao->registerScope('active', function (&$criteria) { $criteria['status'] = 'active'; });
        $activePage = $userDao->active()->paginate(1, 50);
        $this->assertSame(20, $activePage['total']);

        // ad-hoc scope combining additional condition (no-op example)
        $combined = $userDao->scope(function (&$criteria) { if (!isset($criteria['status'])) { $criteria['status'] = 'inactive'; } })
                            ->paginate(1, 100);
        $this->assertSame(15, $combined['total']);
    }
}

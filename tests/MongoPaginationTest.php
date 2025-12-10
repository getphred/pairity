<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;

final class MongoPaginationTest extends TestCase
{
    private function hasMongoExt(): bool { return \extension_loaded('mongodb'); }

    public function testPaginateAndSimplePaginateWithScopes(): void
    {
        if (!$this->hasMongoExt()) { $this->markTestSkipped('ext-mongodb not loaded'); }
        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Inline DTO and DAOs
        $userDto = new class([]) extends AbstractDto {};
        $userDtoClass = \get_class($userDto);
        $postDto = new class([]) extends AbstractDto {};
        $postDtoClass = \get_class($postDto);

        $PostDao = new class($conn, $postDtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c, string $dto){ parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.pg_posts'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        $UserDao = new class($conn, $userDtoClass, get_class($PostDao)) extends AbstractMongoDao {
            private string $dto; private string $postDaoClass; public function __construct($c,string $dto,string $p){ parent::__construct($c); $this->dto=$dto; $this->postDaoClass=$p; }
            protected function collection(): string { return 'pairity_test.pg_users'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [
                'posts' => [ 'type' => 'hasMany', 'dao' => $this->postDaoClass, 'foreignKey' => 'user_id', 'localKey' => '_id' ],
            ]; }
        };

        $postDao = new $PostDao($conn, $postDtoClass);
        $userDao = new $UserDao($conn, $userDtoClass, get_class($postDao));

        // Clean
        foreach ($userDao->findAllBy([]) as $u) { $id = (string)($u->toArray(false)['_id'] ?? ''); if ($id) { $userDao->deleteById($id); } }
        foreach ($postDao->findAllBy([]) as $p) { $id = (string)($p->toArray(false)['_id'] ?? ''); if ($id) { $postDao->deleteById($id); } }

        // Seed 26 users; attach posts to some
        for ($i=1; $i<=26; $i++) {
            $status = $i % 2 === 0 ? 'active' : 'inactive';
            $u = $userDao->insert(['email' => "m{$i}@ex.com", 'status' => $status]);
            $uid = (string)($u->toArray(false)['_id'] ?? '');
            if ($i % 4 === 0) { $postDao->insert(['user_id' => $uid, 'title' => 'T'.$i]); }
        }

        // Paginate
        $page = $userDao->paginate(2, 10, []);
        $this->assertSame(26, $page['total']);
        $this->assertCount(10, $page['data']);
        $this->assertSame(3, $page['lastPage']);

        // Simple paginate last page nextPage null
        $sp = $userDao->simplePaginate(3, 10, []);
        $this->assertNull($sp['nextPage']);

        // fields + sort + with on paginate
        $with = $userDao->fields('email','posts.title')->sort(['email' => 1])->with(['posts'])->paginate(1, 5);
        $this->assertNotEmpty($with['data']);
        $first = $with['data'][0]->toArray(false);
        $this->assertArrayHasKey('email', $first);
        $this->assertArrayHasKey('posts', $first);

        // Scopes
        $userDao->registerScope('active', function (&$filter) { $filter['status'] = 'active'; });
        $active = $userDao->active()->paginate(1, 100, []);
        // Half of 26 rounded down
        $this->assertSame(13, $active['total']);
    }
}

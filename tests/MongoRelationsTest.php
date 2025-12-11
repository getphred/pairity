<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;

/**
 * @group mongo-integration
 */
final class MongoRelationsTest extends TestCase
{
    private function hasMongoExt(): bool { return \extension_loaded('mongodb'); }

    public function testEagerAndLazyRelations(): void
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
        // Ping server to ensure availability
        try {
            $conn->getClient()->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Inline DTO classes
        $userDto = new class([]) extends AbstractDto {};
        $userDtoClass = \get_class($userDto);
        $postDto = new class([]) extends AbstractDto {};
        $postDtoClass = \get_class($postDto);

        // Inline DAOs with relations
        $UserDao = new class($conn, $userDtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.users_rel'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [
                'posts' => [ 'type' => 'hasMany', 'dao' => get_class($this->makePostDao()), 'foreignKey' => 'user_id', 'localKey' => '_id' ],
            ]; }
            private function makePostDao(): object { return new class($this->getConnection(), 'stdClass') extends AbstractMongoDao {
                private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
                protected function collection(): string { return 'pairity_test.posts_rel'; }
                protected function dtoClass(): string { return $this->dto; }
            }; }
        };

        $PostDao = new class($conn, $postDtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
            protected function collection(): string { return 'pairity_test.posts_rel'; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [
                'user' => [ 'type' => 'belongsTo', 'dao' => get_class($this->makeUserDao()), 'foreignKey' => 'user_id', 'otherKey' => '_id' ],
            ]; }
            private function makeUserDao(): object { return new class($this->getConnection(), 'stdClass') extends AbstractMongoDao {
                private string $dto; public function __construct($c, string $dto) { parent::__construct($c); $this->dto = $dto; }
                protected function collection(): string { return 'pairity_test.users_rel'; }
                protected function dtoClass(): string { return $this->dto; }
            }; }
        };

        // Instantiate concrete DAOs for use
        $userDao = new $UserDao($conn, $userDtoClass);
        $postDao = new $PostDao($conn, $postDtoClass);

        // Clean
        foreach ($postDao->findAllBy([]) as $p) { $postDao->deleteById((string)($p->toArray(false)['_id'] ?? '')); }
        foreach ($userDao->findAllBy([]) as $u) { $userDao->deleteById((string)($u->toArray(false)['_id'] ?? '')); }

        // Seed one user and two posts
        $u = $userDao->insert(['email' => 'r@example.com', 'name' => 'Rel']);
        $uid = (string)$u->toArray(false)['_id'];
        $postDao->insert(['title' => 'A', 'user_id' => $uid]);
        $postDao->insert(['title' => 'B', 'user_id' => $uid]);

        // Eager load posts on users
        $users = $userDao->with(['posts'])->findAllBy([]);
        $this->assertNotEmpty($users);
        $this->assertIsArray($users[0]->toArray(false)['posts'] ?? null);

        // Lazy load belongsTo on a post
        $one = $postDao->findOneBy(['title' => 'A']);
        $this->assertNotNull($one);
        $postDao->load($one, 'user');
        $this->assertNotNull($one->toArray(false)['user'] ?? null);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Orm\UnitOfWork;
use Pairity\Model\AbstractDto;

final class UnitOfWorkCascadeMongoTest extends TestCase
{
    private function hasMongoExt(): bool
    {
        return \extension_loaded('mongodb');
    }

    public function testDeleteByIdCascadesToChildren(): void
    {
        if (!$this->hasMongoExt()) {
            $this->markTestSkipped('ext-mongodb not loaded');
        }

        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        // Inline DTOs
        $userDto = new class([]) extends AbstractDto {};
        $userDtoClass = \get_class($userDto);
        $postDto = new class([]) extends AbstractDto {};
        $postDtoClass = \get_class($postDto);

        // Inline DAOs with relation and cascadeDelete=true
        $UserDao = new class($conn, $userDtoClass, $postDtoClass) extends AbstractMongoDao {
            private string $userDto; private string $postDto; public function __construct($c, string $u, string $p) { parent::__construct($c); $this->userDto = $u; $this->postDto = $p; }
            protected function collection(): string { return 'pairity_test.uow_users_cascade'; }
            protected function dtoClass(): string { return $this->userDto; }
            protected function relations(): array {
                return [
                    'posts' => [
                        'type' => 'hasMany',
                        'dao'  => get_class(new class($this->getConnection(), $this->postDto) extends AbstractMongoDao {
                            private string $dto; public function __construct($c, string $d) { parent::__construct($c); $this->dto = $d; }
                            protected function collection(): string { return 'pairity_test.uow_posts_cascade'; }
                            protected function dtoClass(): string { return $this->dto; }
                        }),
                        'foreignKey' => 'user_id',
                        'localKey'   => '_id',
                        'cascadeDelete' => true,
                    ],
                ];
            }
        };

        $PostDao = new class($conn, $postDtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c, string $d) { parent::__construct($c); $this->dto = $d; }
            protected function collection(): string { return 'pairity_test.uow_posts_cascade'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        $userDao = new $UserDao($conn, $userDtoClass, $postDtoClass);
        $postDao = new $PostDao($conn, $postDtoClass);

        // Clean
        foreach ($postDao->findAllBy([]) as $p) { $id = (string)($p->toArray(false)['_id'] ?? ''); if ($id) { $postDao->deleteById($id); } }
        foreach ($userDao->findAllBy([]) as $u) { $id = (string)($u->toArray(false)['_id'] ?? ''); if ($id) { $userDao->deleteById($id); } }

        // Seed
        $u = $userDao->insert(['email' => 'c@example.com']);
        $uid = (string)($u->toArray(false)['_id'] ?? '');
        $postDao->insert(['user_id' => $uid, 'title' => 'A']);
        $postDao->insert(['user_id' => $uid, 'title' => 'B']);

        // UoW: delete parent -> children should be deleted first
        UnitOfWork::run(function() use ($userDao, $uid) {
            $userDao->deleteById($uid);
        });

        // Verify
        $children = $postDao->findAllBy(['user_id' => $uid]);
        $this->assertCount(0, $children, 'Child posts should be deleted via cascade');
        $this->assertNull($userDao->findById($uid));
    }
}

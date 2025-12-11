<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class BelongsToManySqliteTest extends TestCase
{
    private function conn()
    {
        return ConnectionManager::make(['driver' => 'sqlite', 'path' => ':memory:']);
    }

    public function testBelongsToManyEagerAndPivotHelpers(): void
    {
        $conn = $this->conn();
        // schema
        $conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
        $conn->execute('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $conn->execute('CREATE TABLE user_role (user_id INTEGER, role_id INTEGER)');

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $RoleDto = new class([]) extends AbstractDto {};
        $userDtoClass = get_class($UserDto);
        $roleDtoClass = get_class($RoleDto);

        // DAOs
        $RoleDao = new class($conn) extends AbstractDao {
            public static string $dto;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'roles'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };
        $roleDaoClass = get_class($RoleDao);
        $roleDaoClass::$dto = $roleDtoClass;

        $UserDao = new class($conn) extends AbstractDao {
            public static string $dto; public static string $roleDaoClass;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return 'users'; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
            protected function relations(): array {
                return [
                    'roles' => [
                        'type' => 'belongsToMany',
                        'dao'  => self::$roleDaoClass,
                        'pivot' => 'user_role',
                        'foreignPivotKey' => 'user_id',
                        'relatedPivotKey' => 'role_id',
                        'localKey' => 'id',
                        'relatedKey' => 'id',
                    ],
                ];
            }
        };

        $userDaoClass = get_class($UserDao);
        $userDaoClass::$dto = $userDtoClass;
        $userDaoClass::$roleDaoClass = $roleDaoClass;

        $roleDao = new $roleDaoClass($conn);
        $userDao = new $userDaoClass($conn);

        // seed
        $u = $userDao->insert(['email' => 'p@example.com']);
        $uid = (int)($u->toArray(false)['id'] ?? 0);
        $r1 = $roleDao->insert(['name' => 'admin']);
        $r2 = $roleDao->insert(['name' => 'editor']);
        $rid1 = (int)($r1->toArray(false)['id'] ?? 0);
        $rid2 = (int)($r2->toArray(false)['id'] ?? 0);

        // attach via helper
        $userDao->attach('roles', $uid, [$rid1, $rid2]);

        // eager load roles
        $loaded = $userDao->with(['roles'])->findById($uid);
        $this->assertNotNull($loaded);
        $roles = $loaded->toArray(false)['roles'] ?? [];
        $this->assertIsArray($roles);
        $this->assertCount(2, $roles);

        // detach one
        $det = $userDao->detach('roles', $uid, [$rid1]);
        $this->assertGreaterThanOrEqual(1, $det);
        $reloaded = $userDao->with(['roles'])->findById($uid);
        $this->assertCount(1, $reloaded->toArray(false)['roles'] ?? []);

        // sync to only [rid2]
        $res = $userDao->sync('roles', $uid, [$rid2]);
        $this->assertIsArray($res);
        $synced = $userDao->with(['roles'])->findById($uid);
        $this->assertCount(1, $synced->toArray(false)['roles'] ?? []);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class BelongsToManyMysqlTest extends TestCase
{
    private function mysqlConfig(): array
    {
        $host = getenv('MYSQL_HOST') ?: null;
        if (!$host) {
            $this->markTestSkipped('MYSQL_HOST not set; skipping MySQL belongsToMany test');
        }
        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
            'database' => getenv('MYSQL_DB') ?: 'pairity',
            'username' => getenv('MYSQL_USER') ?: 'root',
            'password' => getenv('MYSQL_PASS') ?: 'root',
            'charset' => 'utf8mb4',
        ];
    }

    public function testBelongsToManyEagerAndHelpers(): void
    {
        $cfg = $this->mysqlConfig();
        $conn = ConnectionManager::make($cfg);
        $schema = SchemaManager::forConnection($conn);

        // unique table names per run
        $suffix = substr(sha1((string)microtime(true)), 0, 6);
        $usersT = 'u_btm_' . $suffix;
        $rolesT = 'r_btm_' . $suffix;
        $pivotT = 'ur_btm_' . $suffix;

        // Create tables
        $schema->create($usersT, function (Blueprint $t) { $t->increments('id'); $t->string('email', 190); });
        $schema->create($rolesT, function (Blueprint $t) { $t->increments('id'); $t->string('name', 190); });
        $conn->execute("CREATE TABLE `{$pivotT}` (user_id INT NOT NULL, role_id INT NOT NULL)");

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $RoleDto = new class([]) extends AbstractDto {};
        $userDto = get_class($UserDto); $roleDto = get_class($RoleDto);

        // DAOs (constructors accept only connection; runtime configuration via static props)
        $RoleDao = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $UserDao = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto; public static string $roleDaoClass; public static string $pivot;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function relations(): array {
                return [
                    'roles' => [
                        'type' => 'belongsToMany',
                        'dao'  => self::$roleDaoClass,
                        'pivot' => self::$pivot,
                        'foreignPivotKey' => 'user_id',
                        'relatedPivotKey' => 'role_id',
                        'localKey' => 'id',
                        'relatedKey' => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
        };

        // Configure static props for DAOs
        $roleDaoClass = get_class($RoleDao);
        $roleDaoClass::$table = $rolesT;
        $roleDaoClass::$dto = $roleDto;

        $userDaoClass = get_class($UserDao);
        $userDaoClass::$table = $usersT;
        $userDaoClass::$dto = $userDto;
        $userDaoClass::$roleDaoClass = $roleDaoClass;
        $userDaoClass::$pivot = $pivotT;

        $roleDao = new $roleDaoClass($conn);
        $userDao = new $userDaoClass($conn);

        // Seed
        $u = $userDao->insert(['email' => 'b@example.com']);
        $uid = (int)($u->toArray(false)['id'] ?? 0);
        $r1 = $roleDao->insert(['name' => 'admin']);
        $r2 = $roleDao->insert(['name' => 'editor']);
        $rid1 = (int)($r1->toArray(false)['id'] ?? 0); $rid2 = (int)($r2->toArray(false)['id'] ?? 0);

        $userDao->attach('roles', $uid, [$rid1, $rid2]);

        $loaded = $userDao->with(['roles'])->findById($uid);
        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded->toArray(false)['roles'] ?? []);

        $userDao->detach('roles', $uid, [$rid1]);
        $re = $userDao->with(['roles'])->findById($uid);
        $this->assertCount(1, $re->toArray(false)['roles'] ?? []);

        $userDao->sync('roles', $uid, [$rid2]);
        $re2 = $userDao->with(['roles'])->findById($uid);
        $this->assertCount(1, $re2->toArray(false)['roles'] ?? []);

        // Cleanup
        $schema->drop($usersT);
        $schema->drop($rolesT);
        $conn->execute('DROP TABLE `' . $pivotT . '`');
    }
}

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

        // DAOs
        $RoleDao = new class($conn, $rolesT, $roleDto) extends AbstractDao {
            private string $table; private string $dto;
            public function __construct($c, string $table, string $dto) { parent::__construct($c); $this->table = $table; $this->dto = $dto; }
            public function getTable(): string { return $this->table; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $UserDao = new class($conn, $usersT, $userDto, get_class($RoleDao), $pivotT) extends AbstractDao {
            private string $table; private string $dto; private string $roleDaoClass; private string $pivot;
            public function __construct($c, string $table, string $dto, string $roleDaoClass, string $pivot) { parent::__construct($c); $this->table=$table; $this->dto=$dto; $this->roleDaoClass=$roleDaoClass; $this->pivot=$pivot; }
            public function getTable(): string { return $this->table; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array {
                return [
                    'roles' => [
                        'type' => 'belongsToMany',
                        'dao'  => $this->roleDaoClass,
                        'pivot' => $this->pivot,
                        'foreignPivotKey' => 'user_id',
                        'relatedPivotKey' => 'role_id',
                        'localKey' => 'id',
                        'relatedKey' => 'id',
                    ],
                ];
            }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
        };

        $roleDao = new $RoleDao($conn, $rolesT, $roleDto);
        $userDao = new $UserDao($conn, $usersT, $userDto, get_class($roleDao), $pivotT);

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

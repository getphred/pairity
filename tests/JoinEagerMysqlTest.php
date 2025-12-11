<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

final class JoinEagerMysqlTest extends TestCase
{
    private function mysqlConfig(): array
    {
        $host = getenv('MYSQL_HOST') ?: null;
        if (!$host) {
            $this->markTestSkipped('MYSQL_HOST not set; skipping MySQL join eager test');
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

    public function testJoinEagerHasManyAndBelongsTo(): void
    {
        $cfg = $this->mysqlConfig();
        $conn = ConnectionManager::make($cfg);
        $schema = SchemaManager::forConnection($conn);

        // Unique table names per run
        $suf = substr(sha1((string)microtime(true)), 0, 6);
        $usersT = 'je_users_' . $suf;
        $postsT = 'je_posts_' . $suf;

        // Create tables
        $schema->create($usersT, function (Blueprint $t) { $t->increments('id'); $t->string('name', 190); });
        $schema->create($postsT, function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->string('title', 190); $t->datetime('deleted_at')->nullable(); });

        // DTOs
        $UserDto = new class([]) extends AbstractDto {};
        $PostDto = new class([]) extends AbstractDto {};
        $uClass = get_class($UserDto); $pClass = get_class($PostDto);

        // DAOs (constructors accept only connection; configured via static props)
        $PostDao = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return [
                'primaryKey' => 'id',
                'columns' => [ 'id'=>['cast'=>'int'], 'user_id'=>['cast'=>'int'], 'title'=>['cast'=>'string'], 'deleted_at'=>['cast'=>'datetime'] ],
                'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
            ]; }
        };

        $UserDao = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto; public static string $postDaoClass;
            public function __construct($c) { parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function relations(): array { return [ 'posts' => [ 'type'=>'hasMany', 'dao'=>self::$postDaoClass, 'foreignKey'=>'user_id', 'localKey'=>'id' ] ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        // Configure static props
        $postDaoClass = get_class($PostDao);
        $postDaoClass::$table = $postsT;
        $postDaoClass::$dto = $pClass;
        $userDaoClass = get_class($UserDao);
        $userDaoClass::$table = $usersT;
        $userDaoClass::$dto = $uClass;
        $userDaoClass::$postDaoClass = $postDaoClass;

        $postDao = new $postDaoClass($conn);
        $userDao = new $userDaoClass($conn);

        // Seed
        $u1 = $userDao->insert(['name' => 'Alice']);
        $u2 = $userDao->insert(['name' => 'Bob']);
        $uid1 = (int)$u1->toArray(false)['id'];
        $uid2 = (int)$u2->toArray(false)['id'];
        $postDao->insert(['user_id' => $uid1, 'title' => 'P1']);
        $postDao->insert(['user_id' => $uid1, 'title' => 'P2']);
        // soft-deleted child for Bob
        $postDao->insert(['user_id' => $uid2, 'title' => 'Hidden', 'deleted_at' => gmdate('Y-m-d H:i:s')]);

        // Baseline batched eager (include posts.user_id for grouping)
        $baseline = $userDao->fields('id','name','posts.user_id','posts.title')->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $baseline);
        $postsAlice = $baseline[0]->toArray(false)['posts'] ?? [];
        $this->assertIsArray($postsAlice);
        $this->assertCount(2, $postsAlice);

        // Join-based eager (global)
        $joined = $userDao->fields('id','name','posts.user_id','posts.title')->useJoinEager()->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $joined);
        foreach ($joined as $u) {
            $posts = $u->toArray(false)['posts'] ?? [];
            foreach ($posts as $p) {
                $this->assertNotSame('Hidden', $p->toArray(false)['title'] ?? null);
            }
        }

        // belongsTo join: Posts -> User (use static-prop pattern for both sides)
        $UserDao2 = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto;
            public function __construct($c){ parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };
        $userDao2Class = get_class($UserDao2);
        $userDao2Class::$table = $usersT;
        $userDao2Class::$dto = $uClass;

        $PostDao2 = new class($conn) extends AbstractDao {
            public static string $table; public static string $dto; public static string $userDaoClass;
            public function __construct($c){ parent::__construct($c); }
            public function getTable(): string { return self::$table; }
            protected function dtoClass(): string { return self::$dto; }
            protected function relations(): array { return [ 'user' => [ 'type'=>'belongsTo', 'dao'=>self::$userDaoClass, 'foreignKey'=>'user_id', 'otherKey'=>'id' ] ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };
        $postDao2Class = get_class($PostDao2);
        $postDao2Class::$table = $postsT;
        $postDao2Class::$dto = $pClass;
        $postDao2Class::$userDaoClass = $userDao2Class;

        $postDaoJ = new $postDao2Class($conn);
        $rows = $postDaoJ->fields('id','title','user.name')->useJoinEager()->with(['user'])->findAllBy([]);
        $this->assertNotEmpty($rows);
        $arr = $rows[0]->toArray(false);
        $this->assertArrayHasKey('user', $arr);

        // Cleanup
        $schema->drop($usersT);
        $schema->drop($postsT);
    }
}

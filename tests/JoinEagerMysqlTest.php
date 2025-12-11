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

        // DAOs
        $PostDao = new class($conn, $postsT, $pClass) extends AbstractDao {
            private string $table; private string $dto;
            public function __construct($c, string $table, string $dto) { parent::__construct($c); $this->table=$table; $this->dto=$dto; }
            public function getTable(): string { return $this->table; }
            protected function dtoClass(): string { return $this->dto; }
            protected function schema(): array { return [
                'primaryKey' => 'id',
                'columns' => [ 'id'=>['cast'=>'int'], 'user_id'=>['cast'=>'int'], 'title'=>['cast'=>'string'], 'deleted_at'=>['cast'=>'datetime'] ],
                'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
            ]; }
        };

        $UserDao = new class($conn, $usersT, $uClass, get_class($PostDao)) extends AbstractDao {
            private string $table; private string $dto; private string $postDaoClass;
            public function __construct($c, string $table, string $dto, string $postDaoClass) { parent::__construct($c); $this->table=$table; $this->dto=$dto; $this->postDaoClass=$postDaoClass; }
            public function getTable(): string { return $this->table; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [ 'posts' => [ 'type'=>'hasMany', 'dao'=>$this->postDaoClass, 'foreignKey'=>'user_id', 'localKey'=>'id' ] ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
        };

        $postDao = new $PostDao($conn, $postsT, $pClass);
        $userDao = new $UserDao($conn, $usersT, $uClass, get_class($postDao));

        // Seed
        $u1 = $userDao->insert(['name' => 'Alice']);
        $u2 = $userDao->insert(['name' => 'Bob']);
        $uid1 = (int)$u1->toArray(false)['id'];
        $uid2 = (int)$u2->toArray(false)['id'];
        $postDao->insert(['user_id' => $uid1, 'title' => 'P1']);
        $postDao->insert(['user_id' => $uid1, 'title' => 'P2']);
        // soft-deleted child for Bob
        $postDao->insert(['user_id' => $uid2, 'title' => 'Hidden', 'deleted_at' => gmdate('Y-m-d H:i:s')]);

        // Baseline batched eager
        $baseline = $userDao->fields('id','name','posts.title')->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $baseline);
        $postsAlice = $baseline[0]->toArray(false)['posts'] ?? [];
        $this->assertIsArray($postsAlice);
        $this->assertCount(2, $postsAlice);

        // Join-based eager (global)
        $joined = $userDao->fields('id','name','posts.title')->useJoinEager()->with(['posts'])->findAllBy([]);
        $this->assertCount(2, $joined);
        foreach ($joined as $u) {
            $posts = $u->toArray(false)['posts'] ?? [];
            foreach ($posts as $p) {
                $this->assertNotSame('Hidden', $p->toArray(false)['title'] ?? null);
            }
        }

        // belongsTo join: Posts -> User
        $UserDao2 = get_class($userDao);
        $PostDao2 = new class($conn, $postsT, $pClass, $UserDao2, $usersT, $uClass) extends AbstractDao {
            private string $pTable; private string $dto; private string $userDaoClass; private string $uTable; private string $uDto;
            public function __construct($c,string $pTable,string $dto,string $userDaoClass,string $uTable,string $uDto){ parent::__construct($c); $this->pTable=$pTable; $this->dto=$dto; $this->userDaoClass=$userDaoClass; $this->uTable=$uTable; $this->uDto=$uDto; }
            public function getTable(): string { return $this->pTable; }
            protected function dtoClass(): string { return $this->dto; }
            protected function relations(): array { return [ 'user' => [ 'type'=>'belongsTo', 'dao'=>get_class(new class($this->getConnection(), $this->uTable, $this->uDto) extends AbstractDao { private string $t; private string $d; public function __construct($c,string $t,string $d){ parent::__construct($c); $this->t=$t; $this->d=$d; } public function getTable(): string { return $this->t; } protected function dtoClass(): string { return $this->d; } protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; } }), 'foreignKey'=>'user_id', 'otherKey'=>'id' ] ]; }
            protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
        };
        $postDaoJ = new $PostDao2($conn, $postsT, $pClass, $UserDao2, $usersT, $uClass);
        $rows = $postDaoJ->fields('id','title','user.name')->useJoinEager()->with(['user'])->findAllBy([]);
        $this->assertNotEmpty($rows);
        $arr = $rows[0]->toArray(false);
        $this->assertArrayHasKey('user', $arr);

        // Cleanup
        $schema->drop($usersT);
        $schema->drop($postsT);
    }
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

// Configure MySQL connection (adjust credentials as needed)
$conn = ConnectionManager::make([
    'driver'   => 'mysql',
    'host'     => getenv('MYSQL_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('MYSQL_PORT') ?: 3306),
    'database' => getenv('MYSQL_DB') ?: 'app',
    'username' => getenv('MYSQL_USER') ?: 'root',
    'password' => getenv('MYSQL_PASS') ?: 'secret',
    'charset'  => 'utf8mb4',
]);

// Ensure demo tables (idempotent)
$conn->execute('CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL
)');
$conn->execute('CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  deleted_at DATETIME NULL
)');

class UserDto extends AbstractDto {}
class PostDto extends AbstractDto {}

class PostDao extends AbstractDao {
    public function getTable(): string { return 'posts'; }
    protected function dtoClass(): string { return PostDto::class; }
    protected function schema(): array { return [
        'primaryKey' => 'id',
        'columns' => [ 'id'=>['cast'=>'int'], 'user_id'=>['cast'=>'int'], 'title'=>['cast'=>'string'], 'deleted_at'=>['cast'=>'datetime'] ],
        'softDeletes' => ['enabled' => true, 'deletedAt' => 'deleted_at'],
    ]; }
}

class UserDao extends AbstractDao {
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }
    protected function relations(): array {
        return [
            'posts' => [
                'type' => 'hasMany',
                'dao'  => PostDao::class,
                'foreignKey' => 'user_id',
                'localKey'   => 'id',
            ],
        ];
    }
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
}

$userDao = new UserDao($conn);
$postDao = new PostDao($conn);

// Clean minimal (demo only)
foreach ($userDao->findAllBy() as $u) { $userDao->deleteById((int)$u->toArray(false)['id']); }
foreach ($postDao->findAllBy() as $p) { $postDao->deleteById((int)$p->toArray(false)['id']); }

// Seed
$u1 = $userDao->insert(['name' => 'Alice']);
$u2 = $userDao->insert(['name' => 'Bob']);
$uid1 = (int)$u1->toArray(false)['id'];
$uid2 = (int)$u2->toArray(false)['id'];
$postDao->insert(['user_id' => $uid1, 'title' => 'P1']);
$postDao->insert(['user_id' => $uid1, 'title' => 'P2']);
$postDao->insert(['user_id' => $uid2, 'title' => 'Hidden', 'deleted_at' => gmdate('Y-m-d H:i:s')]);

// Baseline portable eager (batched IN)
$batched = $userDao->fields('id','name','posts.title')->with(['posts'])->findAllBy([]);
echo "Batched eager: \n";
foreach ($batched as $u) {
    $arr = $u->toArray(false);
    $titles = array_map(fn($p) => $p->toArray(false)['title'] ?? '', $arr['posts'] ?? []);
    echo "- {$arr['name']} posts: " . implode(', ', $titles) . "\n";
}

// Join-based eager (global opt-in)
$joined = $userDao->fields('id','name','posts.title')->useJoinEager()->with(['posts'])->findAllBy([]);
echo "\nJoin eager (global): \n";
foreach ($joined as $u) {
    $arr = $u->toArray(false);
    $titles = array_map(fn($p) => $p->toArray(false)['title'] ?? '', $arr['posts'] ?? []);
    echo "- {$arr['name']} posts: " . implode(', ', $titles) . "\n";
}

// Per-relation join hint (equivalent in this single-rel case)
$hinted = $userDao->fields('id','name','posts.title')
    ->with(['posts' => ['strategy' => 'join']])
    ->findAllBy([]); // will fallback to batched if conditions not met
echo "\nJoin eager (per-relation hint): \n";
foreach ($hinted as $u) {
    $arr = $u->toArray(false);
    $titles = array_map(fn($p) => $p->toArray(false)['title'] ?? '', $arr['posts'] ?? []);
    echo "- {$arr['name']} posts: " . implode(', ', $titles) . "\n";
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

// SQLite connection (file db.sqlite in project root)
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

// Demo tables
$conn->execute('CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT,
  status TEXT
)');
$conn->execute('CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  title TEXT
)');

class UserDto extends AbstractDto {}
class PostDto extends AbstractDto {}

class PostDao extends AbstractDao {
    public function getTable(): string { return 'posts'; }
    protected function dtoClass(): string { return PostDto::class; }
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'user_id'=>['cast'=>'int'],'title'=>['cast'=>'string']]]; }
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
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string'],'status'=>['cast'=>'string']]]; }
}

$userDao = new UserDao($conn);
$postDao = new PostDao($conn);

// Seed a few users if table is empty
$hasAny = $userDao->findAllBy();
if (!$hasAny) {
    for ($i=1; $i<=25; $i++) {
        $status = $i % 2 === 0 ? 'active' : 'inactive';
        $u = $userDao->insert(['email' => "p{$i}@example.com", 'status' => $status]);
        $uid = (int)($u->toArray(false)['id'] ?? 0);
        if ($i % 5 === 0) { $postDao->insert(['user_id' => $uid, 'title' => 'Hello '.$i]); }
    }
}

// Paginate (page 1, perPage 10)
$page1 = $userDao->paginate(1, 10);
echo "Page 1: total={$page1['total']} lastPage={$page1['lastPage']} count=".count($page1['data'])."\n";

// Simple paginate (detect next page)
$sp = $userDao->simplePaginate(1, 10);
echo 'Simple nextPage: ' . json_encode($sp['nextPage']) . "\n";

// Projection + eager load
$with = $userDao->fields('id','email','posts.title')->with(['posts'])->paginate(1, 5);
echo 'With posts: ' . json_encode(array_map(fn($d) => $d->toArray(), $with['data'])) . "\n";

// Named scope
$userDao->registerScope('active', function (&$criteria) { $criteria['status'] = 'active'; });
$active = $userDao->active()->paginate(1, 50);
echo "Active total: {$active['total']}\n";

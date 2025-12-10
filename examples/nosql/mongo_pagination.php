<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;

// Connect via URI or discrete params
$conn = MongoConnectionManager::make([
    // 'uri' => 'mongodb://user:pass@127.0.0.1:27017/?authSource=admin',
    'host' => '127.0.0.1',
    'port' => 27017,
]);

class UserDoc extends AbstractDto {}
class PostDoc extends AbstractDto {}

class PostMongoDao extends AbstractMongoDao
{
    protected function collection(): string { return 'pairity_demo.pg_posts'; }
    protected function dtoClass(): string { return PostDoc::class; }
}

class UserMongoDao extends AbstractMongoDao
{
    protected function collection(): string { return 'pairity_demo.pg_users'; }
    protected function dtoClass(): string { return UserDoc::class; }
    protected function relations(): array
    {
        return [
            'posts' => [
                'type' => 'hasMany',
                'dao'  => PostMongoDao::class,
                'foreignKey' => 'user_id',
                'localKey'   => '_id',
            ],
        ];
    }
}

$userDao = new UserMongoDao($conn);
$postDao = new PostMongoDao($conn);

// Clean collections for demo
foreach ($userDao->findAllBy([]) as $u) { $id = (string)($u->toArray(false)['_id'] ?? ''); if ($id) { $userDao->deleteById($id); } }
foreach ($postDao->findAllBy([]) as $p) { $id = (string)($p->toArray(false)['_id'] ?? ''); if ($id) { $postDao->deleteById($id); } }

// Seed 22 users; every 3rd has a post
for ($i=1; $i<=22; $i++) {
    $status = $i % 2 === 0 ? 'active' : 'inactive';
    $u = $userDao->insert(['email' => "mp{$i}@example.com", 'status' => $status]);
    $uid = (string)($u->toArray(false)['_id'] ?? '');
    if ($i % 3 === 0) { $postDao->insert(['user_id' => $uid, 'title' => 'Post '.$i]); }
}

// Paginate
$page1 = $userDao->paginate(1, 10, []);
echo "Page1 total={$page1['total']} lastPage={$page1['lastPage']} count=".count($page1['data'])."\n";

// Simple paginate
$sp = $userDao->simplePaginate(3, 10, []);
echo 'Simple nextPage on page 3: ' . json_encode($sp['nextPage']) . "\n";

// Projection + sort + eager relation
$with = $userDao->fields('email','posts.title')->sort(['email' => 1])->with(['posts'])->paginate(1, 5, []);
echo 'With posts: ' . json_encode(array_map(fn($d) => $d->toArray(), $with['data'])) . "\n";

// Named scope example
$userDao->registerScope('active', function (&$filter) { $filter['status'] = 'active'; });
$active = $userDao->active()->paginate(1, 100, []);
echo "Active total: {$active['total']}\n";

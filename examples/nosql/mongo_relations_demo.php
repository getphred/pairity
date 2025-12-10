<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;

class UserDoc extends AbstractDto {}
class PostDoc extends AbstractDto {}

class UserMongoDao extends AbstractMongoDao
{
    protected function collection(): string { return 'pairity_demo.users'; }
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

class PostMongoDao extends AbstractMongoDao
{
    protected function collection(): string { return 'pairity_demo.posts'; }
    protected function dtoClass(): string { return PostDoc::class; }
    protected function relations(): array
    {
        return [
            'user' => [
                'type' => 'belongsTo',
                'dao'  => UserMongoDao::class,
                'foreignKey' => 'user_id',
                'otherKey'   => '_id',
            ],
        ];
    }
}

$conn = MongoConnectionManager::make([
    'host' => '127.0.0.1',
    'port' => 27017,
]);

$userDao = new UserMongoDao($conn);
$postDao = new PostMongoDao($conn);

// Clean
foreach ($postDao->findAllBy([]) as $p) { $postDao->deleteById((string)$p->toArray(false)['_id']); }
foreach ($userDao->findAllBy([]) as $u) { $userDao->deleteById((string)$u->toArray(false)['_id']); }

// Seed
$u = $userDao->insert(['email' => 'mongo@example.com', 'name' => 'Alice']);
$uid = (string)$u->toArray(false)['_id'];
$p1 = $postDao->insert(['title' => 'First', 'user_id' => $uid]);
$p2 = $postDao->insert(['title' => 'Second', 'user_id' => $uid]);

// Eager load posts on users
$users = $userDao->fields('email', 'name', 'posts.title')->with(['posts'])->findAllBy([]);
echo 'Users with posts: ' . json_encode(array_map(fn($d) => $d->toArray(), $users)) . "\n";

// Lazy load user on a post
$onePost = $postDao->findOneBy(['title' => 'First']);
if ($onePost) {
    $postDao->load($onePost, 'user');
    echo 'Post with user: ' . json_encode($onePost->toArray()) . "\n";
}

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

class UserMongoDao extends AbstractMongoDao
{
    protected function collection(): string { return 'pairity_demo.users'; }
    protected function dtoClass(): string { return UserDoc::class; }
}

$dao = new UserMongoDao($conn);

// Clean for demo
foreach ($dao->findAllBy([]) as $dto) {
    $id = (string)($dto->toArray(false)['_id'] ?? '');
    if ($id) { $dao->deleteById($id); }
}

// Insert
$created = $dao->insert([
    'email' => 'mongo@example.com',
    'name'  => 'Mongo User',
    'status'=> 'active',
]);
echo 'Inserted: ' . json_encode($created->toArray(false)) . "\n";

// Find by dynamic helper
$found = $dao->findOneByEmail('mongo@example.com');
echo 'Found: ' . json_encode($found?->toArray(false)) . "\n";

// Update
if ($found) {
    $id = (string)$found->toArray(false)['_id'];
    $updated = $dao->update($id, ['name' => 'Updated Mongo User']);
    echo 'Updated: ' . json_encode($updated->toArray(false)) . "\n";
}

// Projection + sort + limit
$list = $dao->fields('email', 'name')->sort(['email' => 1])->limit(10)->findAllBy(['status' => 'active']);
echo 'List (projected): ' . json_encode(array_map(fn($d) => $d->toArray(false), $list)) . "\n";

// Delete
if ($found) {
    $id = (string)$found->toArray(false)['_id'];
    $deleted = $dao->deleteById($id);
    echo "Deleted: {$deleted}\n";
}

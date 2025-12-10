<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Pairity\NoSql\Mongo\MongoConnectionManager;

// Configure via URI or discrete params
$conn = MongoConnectionManager::make([
    // 'uri' => 'mongodb://user:pass@127.0.0.1:27017/?authSource=admin',
    'host' => '127.0.0.1',
    'port' => 27017,
]);

$db = 'pairity_demo';
$col = 'users';

// Clean collection for demo
foreach ($conn->find($db, $col, []) as $doc) {
    $conn->deleteOne($db, $col, ['_id' => $doc['_id']]);
}

// Insert
$id = $conn->insertOne($db, $col, [
    'email' => 'mongo@example.com',
    'name'  => 'Mongo User',
    'status'=> 'active',
]);
echo "Inserted _id={$id}\n";

// Find
$found = $conn->find($db, $col, ['_id' => $id]);
echo 'Found: ' . json_encode($found, JSON_UNESCAPED_SLASHES) . PHP_EOL;

// Update
$conn->updateOne($db, $col, ['_id' => $id], ['$set' => ['name' => 'Updated Mongo User']]);
$after = $conn->find($db, $col, ['_id' => $id]);
echo 'After update: ' . json_encode($after, JSON_UNESCAPED_SLASHES) . PHP_EOL;

// Aggregate (simple match projection)
$agg = $conn->aggregate($db, $col, [
    ['$match' => ['status' => 'active']],
    ['$project' => ['email' => 1, 'name' => 1]],
]);
echo 'Aggregate: ' . json_encode($agg, JSON_UNESCAPED_SLASHES) . PHP_EOL;

// Delete
$deleted = $conn->deleteOne($db, $col, ['_id' => $id]);
echo "Deleted: {$deleted}\n";

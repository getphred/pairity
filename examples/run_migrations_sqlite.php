<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Migrations\Migrator;

// SQLite connection (file db.sqlite in project root)
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

// Load migrations (here we just include a PHP file returning a MigrationInterface instance)
$createUsers = require __DIR__ . '/migrations/CreateUsersTable.php';

$migrator = new Migrator($conn);
$migrator->setRegistry([
    'CreateUsersTable' => $createUsers,
]);

// Apply outstanding migrations
$applied = $migrator->migrate([
    'CreateUsersTable' => $createUsers,
]);
echo 'Applied: ' . json_encode($applied) . PHP_EOL;

// To roll back last batch, uncomment:
// $rolled = $migrator->rollback(1);
// echo 'Rolled back: ' . json_encode($rolled) . PHP_EOL;

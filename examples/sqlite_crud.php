<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

// 1) Configure SQLite connection (file db.sqlite in project root)
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

// Create table for demo if not exists
$conn->execute('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    name TEXT,
    status TEXT,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    deleted_at TEXT NULL
)');

// 2) Define DTO, DAO, and Repository for `users` table

class UserDto extends AbstractDto {}

class UserDao extends AbstractDao
{
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }

    // Demonstrate schema metadata (casts)
    protected function schema(): array
    {
        return [
            'primaryKey' => 'id',
            'columns' => [
                'id'     => ['cast' => 'int'],
                'email'  => ['cast' => 'string'],
                'name'   => ['cast' => 'string'],
                'status' => ['cast' => 'string'],
                'created_at' => ['cast' => 'datetime'],
                'updated_at' => ['cast' => 'datetime'],
                'deleted_at' => ['cast' => 'datetime'],
            ],
            'timestamps' => [ 'createdAt' => 'created_at', 'updatedAt' => 'updated_at' ],
            'softDeletes' => [ 'enabled' => true, 'deletedAt' => 'deleted_at' ],
        ];
    }
}

$dao = new UserDao($conn);

// 3) Create (INSERT)
$user = new UserDto([
    'email' => 'bob@example.com',
    'name'  => 'Bob',
    'status'=> 'active',
]);
$created = $dao->insert($user->toArray());
echo "Created user ID: " . ($created->toArray()['id'] ?? 'N/A') . PHP_EOL;

// 4) Read (SELECT)
$found = $dao->findOneBy(['email' => 'bob@example.com']);
echo 'Found: ' . json_encode($found?->toArray()) . PHP_EOL;

// 5) Update
$data = $found?->toArray() ?? [];
$data['name'] = 'Bob Updated';
$updated = $dao->update($data['id'], ['name' => 'Bob Updated']);
echo 'Updated: ' . json_encode($updated->toArray()) . PHP_EOL;

// 6) Delete
// 6) Soft Delete
$deleted = $dao->deleteBy(['email' => 'bob@example.com']);
echo "Soft-deleted rows: {$deleted}" . PHP_EOL;

// 7) Query scopes
$all = $dao->withTrashed()->findAllBy();
echo 'All (with trashed): ' . count($all) . PHP_EOL;
$trashedOnly = $dao->onlyTrashed()->findAllBy();
echo 'Only trashed: ' . count($trashedOnly) . PHP_EOL;

// 8) Restore then force delete
if ($found) {
    $dao->restoreById($found->toArray()['id']);
    echo "Restored ID {$found->toArray()['id']}\n";
    $dao->forceDeleteById($found->toArray()['id']);
    echo "Force-deleted ID {$found->toArray()['id']}\n";
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

// 1) Configure MySQL connection
$conn = ConnectionManager::make([
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'app',
    'username' => 'root',
    'password' => 'secret',
    'charset'  => 'utf8mb4',
]);

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
            ],
            // Uncomment if your table has these columns
            // 'timestamps' => [ 'createdAt' => 'created_at', 'updatedAt' => 'updated_at' ],
            // 'softDeletes' => [ 'enabled' => true, 'deletedAt' => 'deleted_at' ],
        ];
    }
}

$dao = new UserDao($conn);

// 3) Create (INSERT)
$user = new UserDto([
    'email' => 'alice@example.com',
    'name'  => 'Alice',
    'status'=> 'active',
]);
$created = $dao->insert($user->toArray());
echo "Created user ID: " . ($created->toArray()['id'] ?? 'N/A') . PHP_EOL;

// 4) Read (SELECT)
$found = $dao->findOneBy(['email' => 'alice@example.com']);
echo 'Found: ' . json_encode($found?->toArray()) . PHP_EOL;

// 5) Update
$data = $found?->toArray() ?? [];
$data['name'] = 'Alice Updated';
$updated = $dao->update($data['id'], ['name' => 'Alice Updated']);
echo 'Updated: ' . json_encode($updated->toArray()) . PHP_EOL;

// 6) Delete
$deleted = $dao->deleteBy(['email' => 'alice@example.com']);
echo "Deleted rows: {$deleted}" . PHP_EOL;

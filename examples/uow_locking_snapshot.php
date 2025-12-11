<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;
use Pairity\Orm\UnitOfWork;

// SQLite demo DB (local file)
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

// Ensure table
$conn->execute('CREATE TABLE IF NOT EXISTS uow_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  version INTEGER NOT NULL DEFAULT 0
)');

class UserDto extends AbstractDto {}

class UserDao extends AbstractDao {
    public function getTable(): string { return 'uow_users'; }
    protected function dtoClass(): string { return UserDto::class; }
    protected function schema(): array
    {
        return [
            'primaryKey' => 'id',
            'columns' => [
                'id' => ['cast' => 'int'],
                'name' => ['cast' => 'string'],
                'version' => ['cast' => 'int'],
            ],
            // Enable optimistic locking on integer version column
            'locking' => ['type' => 'version', 'column' => 'version'],
        ];
    }
}

$dao = new UserDao($conn);

// Clean for demo
foreach ($dao->findAllBy() as $row) {
    $dao->deleteById((int)($row->toArray(false)['id'] ?? 0));
}

// Create one
$u = $dao->insert(['name' => 'Alice']);
$id = (int)($u->toArray(false)['id'] ?? 0);

// Demonstrate UoW with snapshot diffing: modify DTO then commit
UnitOfWork::run(function(UnitOfWork $uow) use ($dao, $id) {
    // Enable snapshot diffing (optional)
    $uow->enableSnapshots(true);

    // Load and modify the DTO directly (no explicit update call)
    $user = $dao->findById($id);
    if ($user) {
        // mutate DTO attributes
        $user->setRelation('name', 'Alice (uow)');
    }

    // Also stage an explicit update to show coalescing
    $dao->update($id, ['name' => 'Alice (explicit)']);
});

$after = $dao->findById($id);
echo 'After UoW commit: ' . json_encode($after?->toArray(false)) . "\n";

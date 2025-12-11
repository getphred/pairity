<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;
use Pairity\Events\Events;

// SQLite demo DB
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

// Ensure table
$conn->execute('CREATE TABLE IF NOT EXISTS audit_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT,
  name TEXT,
  status TEXT
)');

class UserDto extends AbstractDto {}
class UserDao extends AbstractDao {
    public function getTable(): string { return 'audit_users'; }
    protected function dtoClass(): string { return UserDto::class; }
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>[
        'id'=>['cast'=>'int'],'email'=>['cast'=>'string'],'name'=>['cast'=>'string'],'status'=>['cast'=>'string']
    ]]; }
}

// Simple audit buffer
$audit = [];

// Register listeners
Events::dispatcher()->clear();
Events::dispatcher()->listen('dao.beforeInsert', function(array &$p) {
    if (($p['table'] ?? '') === 'audit_users') {
        // normalize
        $p['data']['email'] = strtolower((string)($p['data']['email'] ?? ''));
    }
});
Events::dispatcher()->listen('dao.afterInsert', function(array &$p) use (&$audit) {
    if (($p['table'] ?? '') === 'audit_users' && isset($p['dto'])) {
        $audit[] = '[afterInsert] id=' . ($p['dto']->toArray(false)['id'] ?? '?');
    }
});
Events::dispatcher()->listen('dao.afterUpdate', function(array &$p) use (&$audit) {
    if (($p['table'] ?? '') === 'audit_users' && isset($p['dto'])) {
        $audit[] = '[afterUpdate] id=' . ($p['dto']->toArray(false)['id'] ?? '?');
    }
});

$dao = new UserDao($conn);

// Clean for demo
foreach ($dao->findAllBy() as $row) { $dao->deleteById((int)$row->toArray(false)['id']); }

// Perform some ops
$u = $dao->insert(['email' => 'AUDIT@EXAMPLE.COM', 'name' => 'Audit Me']);
$id = (int)($u->toArray(false)['id'] ?? 0);
$dao->update($id, ['name' => 'Audited']);

echo "Audit log:\n" . implode("\n", $audit) . "\n";

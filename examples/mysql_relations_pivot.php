<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDao;
use Pairity\Model\AbstractDto;

// Configure MySQL connection (adjust credentials as needed)
$conn = ConnectionManager::make([
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'app',
    'username' => 'root',
    'password' => 'secret',
    'charset'  => 'utf8mb4',
]);

// Ensure demo tables (idempotent)
$conn->execute('CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL
)');
$conn->execute('CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL
)');
$conn->execute('CREATE TABLE IF NOT EXISTS user_role (
  user_id INT NOT NULL,
  role_id INT NOT NULL
)');

class UserDto extends AbstractDto {}
class RoleDto extends AbstractDto {}

class RoleDao extends AbstractDao {
    public function getTable(): string { return 'roles'; }
    protected function dtoClass(): string { return RoleDto::class; }
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'name'=>['cast'=>'string']]]; }
}

class UserDao extends AbstractDao {
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }
    protected function relations(): array {
        return [
            'roles' => [
                'type' => 'belongsToMany',
                'dao'  => RoleDao::class,
                'pivot' => 'user_role',
                'foreignPivotKey' => 'user_id',
                'relatedPivotKey' => 'role_id',
                'localKey' => 'id',
                'relatedKey' => 'id',
            ],
        ];
    }
    protected function schema(): array { return ['primaryKey'=>'id','columns'=>['id'=>['cast'=>'int'],'email'=>['cast'=>'string']]]; }
}

$roleDao = new RoleDao($conn);
$userDao = new UserDao($conn);

// Clean minimal (demo only)
foreach ($userDao->findAllBy() as $u) { $userDao->deleteById((int)$u->toArray(false)['id']); }
foreach ($roleDao->findAllBy() as $r) { $roleDao->deleteById((int)$r->toArray(false)['id']); }
$conn->execute('DELETE FROM user_role');

// Seed
$admin = $roleDao->insert(['name' => 'admin']);
$editor = $roleDao->insert(['name' => 'editor']);
$u = $userDao->insert(['email' => 'pivot@example.com']);
$uid = (int)$u->toArray(false)['id'];
$ridAdmin = (int)$admin->toArray(false)['id'];
$ridEditor = (int)$editor->toArray(false)['id'];

// Attach roles
$userDao->attach('roles', $uid, [$ridAdmin, $ridEditor]);

$with = $userDao->with(['roles'])->findById($uid);
echo "User with roles: " . json_encode($with?->toArray(true)) . "\n";

// Detach one role
$userDao->detach('roles', $uid, [$ridAdmin]);
$with = $userDao->with(['roles'])->findById($uid);
echo "After detach: " . json_encode($with?->toArray(true)) . "\n";

// Sync to only [editor]
$userDao->sync('roles', $uid, [$ridEditor]);
$with = $userDao->with(['roles'])->findById($uid);
echo "After sync: " . json_encode($with?->toArray(true)) . "\n";

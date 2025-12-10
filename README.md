# Pairity

A partitioned‑model PHP ORM (DTO/DAO) with Query Builder, relations, raw SQL helpers, and a portable migrations + schema builder. Namespace: `Pairity\`. Package: `getphred/pairity`.

## Contributing

This is an early foundation. Contributions, discussions, and design proposals are welcome. Please open an issue to coordinate larger features.

## License

MIT

## Installation

- Requirements: PHP >= 8.1, PDO extension for your database(s)
- Install via Composer:

```
composer require getphred/pairity
```

After install, you can use the CLI at `vendor/bin/pairity`.

## Quick start

Minimal example with SQLite (file db.sqlite) and a simple `users` DAO/DTO.

```php
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

// 1) Connect
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/db.sqlite',
]);

// 2) Ensure table exists (demo)
$conn->execute('CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  name TEXT NULL,
  status TEXT NULL
)');

// 3) Define DTO + DAO
class UserDto extends AbstractDto {}
class UserDao extends AbstractDao {
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }
}

// 4) CRUD
$dao = new UserDao($conn);
$created = $dao->insert(['email' => 'a@b.com', 'name' => 'Alice', 'status' => 'active']);
$one = $dao->findById($created->toArray()['id']);
$many = $dao->findAllBy(['status' => 'active']);
$dao->update($created->toArray()['id'], ['name' => 'Alice Updated']);
$dao->deleteById($created->toArray()['id']);
```

For MySQL, configure:

```php
$conn = ConnectionManager::make([
  'driver' => 'mysql',
  'host' => '127.0.0.1',
  'port' => 3306,
  'database' => 'app',
  'username' => 'root',
  'password' => 'secret',
  'charset'  => 'utf8mb4',
]);
```

## Concepts

- DTO (Data Transfer Object): a lightweight data bag. Extend `Pairity\Model\AbstractDto`. Convert to arrays via `toArray(bool $deep = true)`.
- DAO (Data Access Object): table‑focused persistence and relations. Extend `Pairity\Model\AbstractDao` and implement:
  - `getTable(): string`
  - `dtoClass(): string` (class-string of your DTO)
  - Optional: `schema()` for casts, timestamps, soft deletes
  - Optional: `relations()` for `hasOne`/`hasMany`/`belongsTo`
- Relations are DAO‑centric: call `with([...])` to eager load; `load()`/`loadMany()` for lazy.
- Field projection via `fields('id', 'name', 'posts.title')` with dot‑notation for related selects.
- Raw SQL: use `ConnectionInterface::query`, `execute`, `transaction`, `lastInsertId`.
- Query Builder: a simple builder (`Pairity\Query\QueryBuilder`) exists for ad‑hoc SQL composition.

## Dynamic DAO methods

AbstractDao supports dynamic helpers, mapped to column names (Studly/camel to snake_case):

- `findOneBy<Column>($value): ?DTO`
- `findAllBy<Column>($value): DTO[]`
- `updateBy<Column>($value, array $data): int` (returns affected rows)
- `deleteBy<Column>($value): int`

Examples:

```php
$user = $dao->findOneByEmail('a@b.com');
$actives = $dao->findAllByStatus('active');
$dao->updateByEmail('a@b.com', ['name' => 'New Name']);
$dao->deleteByEmail('gone@b.com');
```

## Selecting fields

- Default projection is `SELECT *`.
- Use `fields(...$fields)` to limit columns. You can include relation fields using dot‑notation:

```php
$users = (new UserDao($conn))
  ->fields('id', 'name', 'posts.title')
  ->with(['posts'])
  ->findAllBy(['status' => 'active']);
```

Notes:
- `fields()` affects only the next `find*` call and then resets.
- Relation field selections are passed to the related DAO when eager loading.

## Supported databases

- MySQL/MariaDB
- SQLite
- PostgreSQL
- SQL Server
- Oracle

NoSQL:
- MongoDB (production): `Pairity\NoSql\Mongo\MongoClientConnection` via `mongodb/mongodb` + `ext-mongodb`.
- MongoDB (stub): `Pairity\NoSql\Mongo\MongoConnection` (in‑memory) remains for experimentation without external deps.

### MongoDB (production adapter)

Pairity includes a production‑ready MongoDB adapter that wraps the official `mongodb/mongodb` library.

Requirements:
- PHP `ext-mongodb` (installed in PHP), and Composer dependency `mongodb/mongodb` (already required by this package).

Connect using the `MongoConnectionManager`:

```php
use Pairity\NoSql\Mongo\MongoConnectionManager;

// Option A: Full URI
$conn = MongoConnectionManager::make([
    'uri' => 'mongodb://user:pass@127.0.0.1:27017/?authSource=admin',
]);

// Option B: Discrete params
$conn = MongoConnectionManager::make([
    'host' => '127.0.0.1',
    'port' => 27017,
    // 'username' => 'user',
    // 'password' => 'pass',
    // 'authSource' => 'admin',
    // 'replicaSet' => 'rs0',
    // 'tls' => false,
]);

// Basic CRUD
$db = 'app';
$col = 'users';

$id = $conn->insertOne($db, $col, ['email' => 'mongo@example.com', 'name' => 'Alice']);
$one = $conn->find($db, $col, ['_id' => $id]);
$conn->updateOne($db, $col, ['_id' => $id], ['$set' => ['name' => 'Alice Updated']]);
$conn->deleteOne($db, $col, ['_id' => $id]);
```

Notes:
- `_id` strings that look like 24‑hex ObjectIds are automatically converted to `ObjectId` on input; returned documents convert `ObjectId` back to strings.
- Aggregation pipelines are supported via `$conn->aggregate($db, $collection, $pipeline, $options)`.
- See `examples/nosql/mongo_crud.php` for a runnable demo.

## Raw SQL

Use the `ConnectionInterface` behind your DAO for direct SQL.

```php
use Pairity\Contracts\ConnectionInterface;

// Get connection from DAO
$conn = $dao->getConnection();

// SELECT
$rows = $conn->query('SELECT id, email FROM users WHERE status = :s', ['s' => 'active']);

// INSERT/UPDATE/DELETE
$affected = $conn->execute('UPDATE users SET status = :s WHERE id = :id', ['s' => 'inactive', 'id' => 10]);

// Transaction
$conn->transaction(function (ConnectionInterface $db) {
    $db->execute('INSERT INTO logs(message) VALUES(:m)', ['m' => 'started']);
    // ...
});
```

## Relations (DAO‑centric MVP)

Declare relations in your DAO by overriding `relations()` and use `with()` for eager loading or `load()`/`loadMany()` for lazy loading.

Example: `User hasMany Posts`, `Post belongsTo User`

```php
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

class UserDto extends AbstractDto {}
class PostDto extends AbstractDto {}

class UserDao extends AbstractDao {
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }
    protected function relations(): array {
        return [
            'posts' => [
                'type' => 'hasMany',
                'dao' => PostDao::class,
                'dto' => PostDto::class,
                'foreignKey' => 'user_id', // on posts
                'localKey'   => 'id',      // on users
            ],
        ];
    }
}

class PostDao extends AbstractDao {
    public function getTable(): string { return 'posts'; }
    protected function dtoClass(): string { return PostDto::class; }
    protected function relations(): array {
        return [
            'user' => [
                'type' => 'belongsTo',
                'dao'  => UserDao::class,
                'dto'  => UserDto::class,
                'foreignKey' => 'user_id', // on posts
                'otherKey'   => 'id',      // on users
            ],
        ];
    }
}

$users = (new UserDao($conn))
    ->fields('id', 'name', 'posts.title')
    ->with(['posts'])
    ->findAllBy(['status' => 'active']);

// Lazy load a relation later
$postDao = new PostDao($conn);
$post = $postDao->findOneBy(['id' => 10]);
$postDao->load($post, 'user');
```

Notes:
- Eager loader batches queries using `IN (...)` lookups under the hood.
- Loaded relations are attached onto the DTO under the relation name (e.g., `$user->posts`).
- `hasOne` is supported like `hasMany` but attaches a single DTO instead of a list.

### belongsToMany (SQL) and pivot helpers

Pairity supports many‑to‑many relations for SQL DAOs via a pivot table. Declare `belongsToMany` in your DAO’s `relations()` and use the built‑in pivot helpers `attach`, `detach`, and `sync`.

Relation metadata keys:
- `type` = `belongsToMany`
- `dao` = related DAO class
- `pivot` (or `pivotTable`) = pivot table name
- `foreignPivotKey` = pivot column referencing the parent table
- `relatedPivotKey` = pivot column referencing the related table
- `localKey` = parent primary key column (default `id`)
- `relatedKey` = related primary key column (default `id`)

Example (users ↔ roles):

```php
class UserDao extends AbstractDao {
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
}

$user = $userDao->insert(['email' => 'a@b.com']);
$uid = $user->toArray(false)['id'];
$userDao->attach('roles', $uid, [$roleId1, $roleId2]); // insert into pivot
$userDao->detach('roles', $uid, [$roleId1]);           // delete specific
$userDao->sync('roles', $uid, [$roleId2]);             // make roles exactly this set

$with = $userDao->with(['roles'])->findById($uid);     // eager load related roles
```

See `examples/mysql_relations_pivot.php` for a runnable snippet.

### Nested eager loading

You can request nested eager loading using dot notation. Example: load a user’s posts and each post’s comments:

```php
$users = $userDao->with(['posts.comments'])->findAllBy([...]);
```

Nested eager loading works for SQL and Mongo DAOs. Pairity performs separate batched fetches per relation level to remain portable across drivers.

### Per‑relation constraints

Pass a callable per relation path to customize how the related DAO queries data for that relation. The callable receives the related DAO instance so you can specify fields, ordering, and limits.

- SQL example (per‑relation `fields()` projection and ordering):

```php
$users = $userDao->with([
    'posts' => function (UserPostDao $dao) {
        $dao->fields('id', 'title');
        // $dao->orderBy('created_at DESC'); // if your DAO exposes ordering
    },
    'posts.comments' // nested
])->findAllBy(['status' => 'active']);
```

- Mongo example (projection, sort, limit):

```php
$docs = $userMongoDao->with([
    'posts' => function (PostMongoDao $dao) {
        $dao->fields('title')->sort(['title' => 1])->limit(10);
    },
    'posts.comments'
])->findAllBy([]);
```

Constraints are applied only to the specific relation path they are defined on.

## Model metadata & schema mapping (MVP)

Define schema metadata on your DAO by overriding `schema()`. The schema enables:
- Column casts (storage <-> PHP): `int`, `float`, `bool`, `string`, `datetime`, `json`
- Timestamps automation (`createdAt`, `updatedAt` filled automatically)
- Soft deletes (update `deletedAt` instead of hard delete, with query scopes)

Example:

```php
use Pairity\Model\AbstractDao;

class UserDao extends AbstractDao
{
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }

    // Optional: declare primary key, casts, timestamps, soft deletes
    protected function schema(): array
    {
        return [
            'primaryKey' => 'id',
            'columns' => [
                'id' => ['cast' => 'int'],
                'email' => ['cast' => 'string'],
                'name' => ['cast' => 'string'],
                'status' => ['cast' => 'string'],
                // if present in your table
                'data' => ['cast' => 'json'],
                'created_at' => ['cast' => 'datetime'],
                'updated_at' => ['cast' => 'datetime'],
                'deleted_at' => ['cast' => 'datetime'],
            ],
            'timestamps' => [
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
            ],
            'softDeletes' => [
                'enabled' => true,
                'deletedAt' => 'deleted_at',
            ],
        ];
    }
}

// Usage (defaults to SELECT * unless you call fields())
$users = (new UserDao($conn))
    ->findAllBy(['status' => 'active']);

// Soft delete vs hard delete
(new UserDao($conn))->deleteById(10); // if softDeletes enabled => sets deleted_at timestamp

// Query scopes for soft deletes
$all = (new UserDao($conn))->withTrashed()->findAllBy();     // include soft-deleted rows
$trashedOnly = (new UserDao($conn))->onlyTrashed()->findAllBy(); // only soft-deleted

// Casting on hydration and storage
$user = (new UserDao($conn))->findById(1); // date columns become DateTimeImmutable; json becomes array
$created = (new UserDao($conn))->insert([
    'email' => 'a@b.com',
    'name' => 'Alice',
    'status' => 'active',
    'data' => ['tags' => ['a','b']], // stored as JSON automatically
]);
```

### Timestamps & Soft Deletes

- Configure in your DAO `schema()` using keys:
  - `timestamps` → `['createdAt' => 'created_at', 'updatedAt' => 'updated_at']`
  - `softDeletes` → `['enabled' => true, 'deletedAt' => 'deleted_at']`
- Behavior:
  - On `insert()`, both `created_at` and `updated_at` are auto-filled (UTC `Y-m-d H:i:s`).
  - On `update()` and `updateBy()`, `updated_at` is auto-updated.
  - On `deleteById()` / `deleteBy()`, if soft deletes are enabled, rows are marked by setting `deleted_at` instead of being physically removed.
  - Default queries exclude soft-deleted rows. Use scopes `withTrashed()` and `onlyTrashed()` to modify visibility.
  - Helpers:
    - `restoreById($id)` / `restoreBy($criteria)` — set `deleted_at` to NULL.
    - `forceDeleteById($id)` / `forceDeleteBy($criteria)` — permanently delete.
    - `touch($id)` — update only the `updated_at` column.

Example:

```php
$dao = new UserDao($conn);
$user = $dao->insert(['email' => 'x@y.com']); // created_at/updated_at filled
$dao->update($user->id, ['name' => 'Updated']); // updated_at bumped
$dao->deleteById($user->id); // soft delete
$also = $dao->withTrashed()->findById($user->id); // visible with trashed
$dao->restoreById($user->id); // restore
$dao->forceDeleteById($user->id); // permanent
```

## Migrations & Schema Builder

Pairity ships a lightweight migrations runner and a portable schema builder focused on MySQL and SQLite for v1. You can declare migrations as PHP classes implementing `Pairity\Migrations\MigrationInterface` and build tables with a fluent `Schema` builder.

Supported:
- Table operations: `create`, `drop`, `dropIfExists`, `table(...)` (ALTER)
- Columns: `increments`, `bigIncrements`, `integer`, `bigInteger`, `string(varchar)`, `text`, `boolean`, `json`, `datetime`, `decimal(precision, scale)`, `timestamps()`
- Indexes: `primary([...])`, `unique([...], ?name)`, `index([...], ?name)`
- ALTER (MVP): `add column` (all drivers), `drop column` (MySQL, Postgres, SQL Server; SQLite 3.35+), `rename column` (MySQL 8+/Postgres/SQL Server; SQLite 3.25+), `add/drop index/unique`, `rename table`
- Drivers: MySQL/MariaDB (default), SQLite (auto-detected), PostgreSQL (pgsql), SQL Server (sqlsrv), Oracle (oci)

Example migration (see `examples/migrations/CreateUsersTable.php`):

```php
use Pairity\Migrations\MigrationInterface;
use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

return new class implements MigrationInterface {
    public function up(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('email', 190);
            $t->unique(['email']);
            $t->string('name', 255)->nullable();
            $t->string('status', 50)->nullable();
            $t->timestamps();
        });
    }

    public function down(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->dropIfExists('users');
    }
};
```

Running migrations (SQLite example):

```php
<?php
require __DIR__.'/../vendor/autoload.php';

use Pairity\Database\ConnectionManager;
use Pairity\Migrations\Migrator;

$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/../db.sqlite',
]);

$createUsers = require __DIR__ . '/migrations/CreateUsersTable.php';

$migrator = new Migrator($conn);
$migrator->setRegistry(['CreateUsersTable' => $createUsers]);

$applied = $migrator->migrate(['CreateUsersTable' => $createUsers]);
echo 'Applied: ' . json_encode($applied) . PHP_EOL;
```

Notes:
- The migrations runner tracks applied migrations in a `migrations` table with batches.
- For rollback, keep a registry of name => instance in the same process or use class names that can be autoloaded.
- SQLite has limitations around `ALTER TABLE` operations; this MVP emits native `ALTER TABLE` for supported versions (ADD COLUMN always; RENAME/DROP COLUMN require modern SQLite). For legacy versions, operations may fail; rebuild strategies can be added later.
  - Pairity includes a best-effort table rebuild fallback for legacy SQLite: when `DROP COLUMN`/`RENAME COLUMN` is unsupported, it recreates the table and copies data. Complex constraints/triggers may not be preserved.

### CLI

Pairity ships a tiny CLI for migrations. After `composer install`, the binary is available as `vendor/bin/pairity` or as a project bin if installed as a dependency.

Usage:

```
pairity migrate [--path=DIR] [--config=FILE]
pairity rollback [--steps=N] [--config=FILE]
pairity status [--path=DIR] [--config=FILE]
pairity reset [--config=FILE]
pairity make:migration Name [--path=DIR]
```

Options and environment:

- If `--config=FILE` is provided, it must be a PHP file returning the ConnectionManager config array.
- Otherwise, the CLI reads environment variables:
  - `DB_DRIVER` (mysql|mariadb|pgsql|postgres|postgresql|sqlite|sqlsrv)
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET` (MySQL)
  - `DB_PATH` (SQLite path)
- If nothing is provided, defaults to a SQLite file at `db.sqlite` in project root.

Migration discovery:
- The CLI looks for migrations in `./database/migrations`, then `project/database/migrations`, then `examples/migrations`.
- Each PHP file should `return` a `MigrationInterface` instance (see examples). Files are applied in filename order.

Example ALTER migration (users add bio):

```php
return new class implements MigrationInterface {
    public function up(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->table('users', function (Blueprint $t) {
            $t->string('bio', 500)->nullable();
            $t->index(['status'], 'users_status_index');
        });
    }
    public function down(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->table('users', function (Blueprint $t) {
            $t->dropIndex('users_status_index');
            $t->dropColumn('bio');
        });
    }
};
```

Notes:
- Schema is optional; if omitted, DAOs behave as before (no casting, no timestamps/soft deletes).
- Timestamps use UTC and the format `Y-m-d H:i:s` for portability.
- Default `SELECT` is `*`. To limit columns, use `fields()`; it always takes precedence.

## DTO toArray (deep vs shallow)

DTOs implement `toArray(bool $deep = true)`.

- When `$deep` is true (default): the DTO is converted to an array and any related DTOs (including arrays of DTOs) are recursively converted.
- When `$deep` is false: only the top-level attributes are converted; related DTOs remain as objects.

Example:

```php
$users = (new UserDao($conn))
    ->with(['posts'])
    ->findAllBy(['status' => 'active']);

$deep    = array_map(fn($u) => $u->toArray(), $users);       // deep (default)
$shallow = array_map(fn($u) => $u->toArray(false), $users);  // shallow
```

## Pagination

Both SQL and Mongo DAOs provide pagination helpers that return DTOs alongside metadata. They honor the usual query modifiers:

- SQL: `fields()`, `with([...])` (eager load)
- Mongo: `fields()` (projection), `sort()`, `with([...])`

Methods and return shapes:

```php
// SQL
/** @return array{data: array<int, DTO>, total: int, perPage: int, currentPage: int, lastPage: int} */
$page = $userDao->paginate(page: 2, perPage: 10, criteria: ['status' => 'active']);

/** @return array{data: array<int, DTO>, perPage: int, currentPage: int, nextPage: int|null} */
$simple = $userDao->simplePaginate(page: 1, perPage: 10, criteria: []);

// Mongo
$page = $userMongoDao->paginate(2, 10, /* filter */ []);
$simple = $userMongoDao->simplePaginate(1, 10, /* filter */ []);
```

Example (SQL + SQLite):

```php
$page1 = (new UserDao($conn))->paginate(1, 10);            // total + lastPage included
$sp    = (new UserDao($conn))->simplePaginate(1, 10);       // no total; nextPage detection

// With projection and eager loading
$with  = (new UserDao($conn))
    ->fields('id','email','posts.title')
    ->with(['posts'])
    ->paginate(1, 5);
```

Example (Mongo):

```php
$with = (new UserMongoDao($mongo))
    ->fields('email','posts.title')
    ->sort(['email' => 1])
    ->with(['posts'])
    ->paginate(1, 10, []);
```

See examples: `examples/sqlite_pagination.php` and `examples/nosql/mongo_pagination.php`.

## Query Scopes (MVP)

Define small, reusable filters using scopes. Scopes are reset after each `find*`/`paginate*` call.

- Ad‑hoc scope: `scope(callable $fn)` where `$fn` mutates the criteria/filter array for the next query.
- Named scopes: `registerScope('name', fn (&$criteria, ...$args) => ...)` and then call `$dao->name(...$args)` before `find*`/`paginate*`.

SQL example:

```php
$userDao->registerScope('active', function (&$criteria) { $criteria['status'] = 'active'; });

$active = $userDao->active()->paginate(1, 50);

// Combine with ad‑hoc scope
$inactive = $userDao->scope(function (&$criteria) { $criteria['status'] = 'inactive'; })
                   ->findAllBy();
```

Mongo example (filter scopes):

```php
$userMongoDao->registerScope('active', function (&$filter) { $filter['status'] = 'active'; });
$page = $userMongoDao->active()->paginate(1, 25, []);
```

## Unit of Work (opt-in)

Pairity offers an optional Unit of Work (UoW) that you can enable per block to batch and order mutations atomically, while keeping the familiar DAO/DTO API.

What it gives you:
- Identity Map: the same in-memory DTO instance per `[DAO class + id]` during the UoW scope.
- Deferred mutations: inside a UoW, `update()`, `updateBy()`, `deleteById()`, and `deleteBy()` are queued and executed on commit in a transaction/session.
- Atomicity: SQL paths use a transaction per connection; Mongo uses a session/transaction when supported.

What stays the same:
- Outside a UoW scope, DAOs behave exactly as before (immediate execution).
- Inside a UoW, `insert()` executes immediately to return the real ID.

Basic usage:

```php
use Pairity\Orm\UnitOfWork;

UnitOfWork::run(function(UnitOfWork $uow) use ($userDao, $postDao) {
    $user = $userDao->findById(42);            // managed instance via identity map
    $userDao->update(42, ['name' => 'New']);   // deferred
    $postDao->insert(['user_id' => 42, 'title' => 'Hello']); // immediate (real id)
    $postDao->deleteBy(['title' => 'Old']);    // deferred
}); // commits or rolls back on exception
```

Manual scoping:

```php
$uow = UnitOfWork::begin();
// ... perform DAO calls ...
$uow->commit(); // or $uow->rollback();
```

Caveats and notes:
- Inserts are immediate by design to obtain primary keys; updates/deletes are deferred.
- If you need to force an immediate operation within a UoW (for advanced cases), DAOs use an internal `UnitOfWork::suspendDuring()` helper to avoid re-enqueueing nested calls.
- The UoW MVP does not yet apply cascade rules; ordering is per-connection in enqueue order.

### Relation-aware delete ordering and cascades (MVP)

- When you enable a UoW and enqueue a parent delete via `deleteById()`, Pairity will automatically delete child rows/documents first for relations marked with a cascade flag, then execute the parent delete. This ensures referential integrity without manual orchestration.

- Supported relation types for cascades: `hasMany`, `hasOne`.

- Enable cascades by adding a flag to the relation metadata in your DAO:

```php
protected function relations(): array
{
    return [
        'posts' => [
            'type' => 'hasMany',
            'dao'  => PostDao::class,
            'foreignKey' => 'user_id',
            'localKey'   => 'id',
            'cascadeDelete' => true, // or: 'cascade' => ['delete' => true]
        ],
    ];
}
```

Behavior details:
- Inside `UnitOfWork::run(...)`, enqueuing `UserDao->deleteById($id)` will synthesize and run `PostDao->deleteBy(['user_id' => $id])` before deleting the user.
- Works for both SQL DAOs and Mongo DAOs.
- Current MVP focuses on delete cascades; cascades for updates and more advanced ordering rules can be added later.

## Roadmap

- Relations enhancements:
  - Nested eager loading (e.g., `posts.comments`)
  - `belongsToMany` (pivot tables)
  - Optional join‑based eager loading strategy
- Unit of Work & Identity Map
- Schema builder: broader ALTER coverage and more dialect nuances; better SQLite rebuild for complex constraints
- CLI: additional commands and quality‑of‑life improvements
- Testing matrix and examples for more drivers
- Caching layer and query logging hooks
- Production NoSQL adapters (MongoDB driver integration)
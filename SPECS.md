# Pairity Specifications

## Architecture
Pairity is a partitioned‑model PHP ORM (DTO/DAO) that separates data representation (DTO) from persistence logic (DAO). It includes a Query Builder, relation management, raw SQL helpers, and a portable migrations + schema builder.

### Namespace
`Pairity\`

### Package
`getphred/pairity`

## Core Concepts

### DTO (Data Transfer Object)
A lightweight data bag.
- Extend `Pairity\Model\AbstractDto`.
- Convert to arrays via `toArray(bool $deep = true)`.
- Support for accessors and mutators.

### DAO (Data Access Object)
Table‑focused persistence and relations.
- Extend `Pairity\Model\AbstractDao`.
- Required implementations:
    - `getTable(): string`
    - `dtoClass(): string` (class-string of the DTO)
- Optional implementations:
    - `schema()`: for casts, timestamps, soft deletes, and locking.
    - `relations()`: for defining `hasOne`, `hasMany`, `belongsTo`, and `belongsToMany`.

## Features

### Persistence & CRUD
- `insert(array $data)`: Immediate execution to return real IDs.
- `update($id, array $data)`: Updates by primary key.
- `updateBy(array $criteria, array $data)`: Bulk updates.
- `deleteById($id)`: Deletes by primary key (supports soft deletes).
- `deleteBy(array $criteria)`: Bulk deletes (supports soft deletes).
- `findById($id)`: Find a single DTO by ID.
- `findOneBy(array $criteria)`: Find a single DTO by criteria.
- `findAllBy(array $criteria)`: Find all matching DTOs.

### Dynamic DAO Methods
`AbstractDao` supports dynamic helpers mapped to column names (Studly/camel to snake_case):
- `findOneBy<Column>($value): ?DTO`
- `findAllBy<Column>($value): DTO[]`
- `updateBy<Column>($value, array $data): int`
- `deleteBy<Column>($value): int`

### Projection
- Default projection is `SELECT *`.
- Use `fields(...$fields)` to limit columns. Supports dot-notation for related selects (e.g., `posts.title`).
- `fields()` affects only the next find call and then resets.

### Relations
- Relation types: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`.
- **Eager Loading**:
    - Default: Batched queries using `IN (...)` lookups.
    - Opt-in: Join-based (`useJoinEager()`) for single-level SQL relations.
    - Nested: Supported via dot notation (e.g., `posts.comments`).
- **Lazy Loading**: Via `load()` or `loadMany()` methods.
- **Cascades**: `cascadeDelete` supported for `hasOne` and `hasMany` within a Unit of Work.
- **Pivot Helpers**: `attach`, `detach`, and `sync` for `belongsToMany`.

### Model Metadata & Schema Mapping
Defined via `schema()` method in DAO.
- **Casting**: `int`, `float`, `bool`, `string`, `datetime`, `json`, or custom `CasterInterface`.
- **Timestamps**: `createdAt` and `updatedAt` filled automatically. Uses UTC `Y-m-d H:i:s`.
- **Soft Deletes**:
    - Enabled via `softDeletes` configuration.
    - Query scopes: `withTrashed()`, `onlyTrashed()`.
    - Helpers: `restoreById()`, `forceDeleteById()`.
- **Locking**: Optimistic locking supported via `version` or `timestamp` strategies.

### Unit of Work (UoW)
Optional batching and identity map.
- Identity Map: Same DTO instance per `[DAO + ID]` within the UoW scope.
- Deferred mutations: `update` and `delete` are queued until commit.
- Atomicity: Transactional commit per connection.

### Event System
Lightweight hook system for DAO operations and UoW commits.
- Events: `dao.before*`, `dao.after*`, `uow.beforeCommit`, `uow.afterCommit`.
- Dispatcher and Subscriber interfaces.

### Pagination
- `paginate(page, perPage, criteria)`: Returns data with total, lastPage, etc.
- `simplePaginate(page, perPage, criteria)`: Returns data without total (uses nextPage detection).

### Query Scopes
- Ad-hoc: `scope(callable $fn)`.
- Named: Registered via `registerScope()`.

## Databases

### Supported SQL
- MySQL / MariaDB
- SQLite (including table rebuild fallback for legacy versions)
- PostgreSQL
- SQL Server
- Oracle

### NoSQL (MongoDB)
- Production adapter: Wraps `mongodb/mongodb` library.
- Stub adapter: In-memory for experimentation.
- Supports aggregation pipelines, pagination, and optimistic locking.

## Migrations & Schema Builder
Lightweight runner and portable builder.
- Operations: `create`, `drop`, `dropIfExists`, `table` (ALTER).
- Column types: `increments`, `string`, `text`, `integer`, `boolean`, `json`, `datetime`, `decimal`, `timestamps`.
- Indexing: `primary`, `unique`, `index`.
- CLI: `pairity` binary for `migrate`, `rollback`, `status`, `reset`, `make:migration`.
    - Supports `--pretend` flag for dry-runs (migrate, rollback, reset).
    - Supports `--template` flag for custom migration templates (make:migration).

## Performance
- PDO prepared-statement cache (LRU).
- Query timing hooks.
- Eager loader IN-batching.
- Metadata memoization.
- **Caching Layer**: PSR-16 (Simple Cache) integration for DAO-level caching.
    - Optional per-DAO cache configuration.
    - Automatic invalidation on write operations.
    - Identity Map synchronization for cached DTOs.
    - Support for bulk invalidation (configurable).

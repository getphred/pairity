### Changelog

All notable changes to this project will be documented in this file.

#### Unreleased

- Core ORM (DAO/DTO) with dynamic finders, `fields()` projection, relations (hasOne/hasMany/belongsTo), nested eager loading, per‑relation constraints, and SQL `belongsToMany` with pivot helpers (`attach`, `detach`, `sync`).
- MongoDB production adapter (`ext-mongodb` + `mongodb/mongodb`) and Mongo DAO layer with relations (MVP), projections/sort/limit, pagination, and a small filter builder.
- Pagination helpers for SQL and Mongo: `paginate` and `simplePaginate`.
- Model metadata & schema mapping: column casts (incl. custom casters), timestamps, soft deletes.
- Migrations & Schema Builder (portable): create/drop/alter; CLI (`vendor/bin/pairity`) with migrate/rollback/status/reset/make:migration. Drivers: MySQL/MariaDB, SQLite, PostgreSQL, SQL Server, Oracle.
- Join‑based eager loading (opt‑in, SQL, single‑level) with safe fallbacks.
- Unit of Work (opt‑in): identity map; deferred updates/deletes; relation‑aware delete cascades; optimistic locking; snapshot diffing (flagged); identity map controls; coalescing.
- Event system: DAO and UoW events; listeners/subscribers.
- CI: GitHub Actions matrix (PHP 8.1–8.3) with MySQL + Mongo services; guarded tests.

#### 0.1.0 — 2025-12-11

- Dependencies: upgrade `mongodb/mongodb` to `^2.0` (tested at 2.1.x). Requires `ext-mongodb >= 2.1`.
- Tests: migrate to PHPUnit 10; update `phpunit.xml.dist` and test cases accordingly.
- MongoDB tests: marked as `@group mongo-integration` and skipped by default via `phpunit.xml.dist` group exclusion. Each test pings the server and skips when not available.
- SQL tests: stabilized MySQL `belongsToMany` eager and join-eager tests by aligning anonymous DAO constructors with runtime patterns and adding needed projections.
- PostgreSQL: fix identifier quoting by using double quotes in `PostgresGrammar` (no more backticks in generated DDL). `PostgresSmokeTest` passes when a Postgres instance is available.
- SQLite: portable DDL in schema tests; minor assertion cleanups for accessors/casters.

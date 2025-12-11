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

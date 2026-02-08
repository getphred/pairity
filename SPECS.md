# Pairity Specifications

**Status**: IMPLEMENTATION

## Architecture
Pairity is a partitioned‑model PHP ORM (DTO/DAO) that separates data representation (DTO) from persistence logic (DAO). It includes a Query Builder, relation management, raw SQL helpers, and a user friendly migration system.

- **Current DB Support**:
  - MySQL/MariaDB
  - PostgreSQL
  - SQLite
  - SQL Server
  - Oracle

- **Table Definitions**
  - Create YAML file to create new table.
    - file names are used as table names.
    - `relations` key in the YAML file can be used to define relationships.
    - `validation` key in the YAML file can be used to define validation rules.
    - `indexes` key in the YAML file can be used to define indexes.
    - `primary` key can define composite primary keys.
    - `prefix` can define table-specific prefixes.
    - `tenancy` key to enable automatic tenant scoping.
    - `inheritance` key to define Single Table (STI) or Class Table (CTI) inheritance.
    - `morph` key to define polymorphic relationship targets.
    - `encrypted` flag on columns for transparent AES-256 encryption.
    - `auditable` key to enable automatic change tracking/auditing.
    - `timestamps` key in the YAML file can be used to enable timestamps.
    - `softDeletes` key in the YAML file can be used to enable soft deletes.
  - Support for **Database Views** (mapping DTOs to SQL views).
  - Update YAML file to modify existing tables.
  - Run `pairity migrate` to apply changes.
  - Run `pairity make:model` to generate DTO and DAO classes from YAML files.
- **Schema Builder**:
  - Fluent PHP API for programmatic table creation and alteration.
  - Acts as the underlying engine for YAML-driven migrations.
  - Support for **Schema Blueprints** (PHP-based schema snapshots).
  - Support for **Schema Snapshotting** (`make:yaml:snapshot`) for baseline deployments.
  - Support for **Dry Run** previews via `migrate:dryrun`.
- **Auditing & Change Tracking**
  - Built-in auditing system that automatically tracks changes to DTOs.
  - Enabled via `auditable: true` in YAML schema.
  - Records previous and current values, event type, and timestamps.
  - Integration with `AuditListener` and `Auditor` service.
- **Event Dispatcher & Lifecycle Hooks**
  - Robust event system for model lifecycle: `retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`.
  - Supports halting operations (e.g., validation) via "before" events.
  - Wildcard listener support (e.g., `pairity.model.*`).
- **Models**:
  - **DTOs / Data Transfer Objects**:
    - immutable by default.
    - Supports accessors and mutators.
    - Supports validation.
    - Supports casting (including native PHP Enums, **Custom Cast Types**, and **Encrypted Attributes**).
    - Supports relations (including **Lazy Loading via Ghost Objects** and **Polymorphism**).
    - Supports serialization (`toArray`, `toJson`).
    - Supports hidden/exposed attributes for serialization.
    - Supports **Virtual/Computed Properties**.
    - Supports **Inheritance Mapping** (STI/CTI).
    - Managed via **Identity Map**.
    - Performance boosted by **Pre-Generated Hydrators**.
  - **DAOs / Data Access Objects**:
    - Provide CRUD and **Mass Operations** (Insert, Update, Delete).
    - Support for **Upserts** (Insert or Update).
    - Support for relations (including **Polymorphic relations**).
    - Support for dynamic helpers.
    - Support for Scopes (including Global Scopes, Soft Deletes, **Automatic Multi-Tenancy**, and **Polymorphic Scopes**).
    - Support for Unit of Work (with **Automatic Auditing**).
    - Support for Event System.
    - Support for Migrations.
    - Support for Schema Builder.
    - Returns DTO, **Pairity Collection**, or **Pairity Paginator**.
- **Query Builder**:
    - Supports raw SQL.
    - Supports joins.
    - Supports eager loading (Nested, Constrained, and **Polymorphic**).
    - Supports pagination (Offset and Cursor).
    - Supports **Concurrency Control** (Shared and Exclusive Locks).
    - Supports **Query Result Caching**.
    - Supports **Async/Parallel Query Execution**.
    - Supports scopes.
    - Supports casts.
    - Supports custom helpers.
    - Supports **Read/Write Splitting**.
    - Supports **Query Logging & Profiling**.
    - Supports **Set Operations** (Unions, Intersect, Except).
    - Supports **Driver-Specific Hints & Options**.
    - DOES NOT support NoSQL.
- **Framework Integration**:
  - **Connection Management**:
    - Multi-database support with named connections.
    - Explicit transaction control (`beginTransaction`, `commit`, `rollBack`).
    - Support for **Savepoints** (nested transactions).
    - Support for **Connection Heartbeats** and health checks via `db:check:health`.
    - Support for **Database Interceptors (Middleware)**.
  - **Seeding & Factories**: Built-in support for dummy data and test state generation.
  - **Localization (i18n)**:
    - Environment-driven language selection (`PAIRITY_LOCALE`).
    - Localized exception messages and CLI output.
  - **Metadata Caching**: Caching of hydrated schema to avoid repeated YAML parsing.
  - **Service Provider**: Standardized entry point for framework integration.


### Namespace
`Pairity\`

### Package
`getphred/pairity`

## Core Concepts

### 1. Partitioned Model (DTO/DAO Separation)
Pairity strictly separates data state from persistence logic.
- **DTO (Data Transfer Object)**: Pure, immutable (by default) data carriers representing a single record. They are responsible for data integrity, casting, and validation, but have no knowledge of the database.
- **DAO (Data Access Object)**: The persistence engine for a model. DAOs handle all database communication (CRUD, queries) and act as factories that produce DTOs.

### 2. YAML-Driven Schema
The database schema is defined in YAML files, which serve as the single source of truth. These files are used to:
- Generate and execute migrations.
- Generate DTO and DAO class code to ensure type safety and consistency.

### 3. Unified Query Builder
A fluent PHP interface for constructing SQL queries that works across all supported relational databases. It abstracts driver-specific syntax while allowing raw SQL for complex, non-portable optimizations.

### 4. Unit of Work
The Unit of Work tracks all changes made to DTOs during a business transaction. It coordinates the writing out of these changes and resolves concurrency issues, ensuring that the database remains in a consistent state.

### 5. Event-Driven Architecture
A robust event system that allows developers to hook into the lifecycle of DTOs and DAOs (e.g., `beforeSave`, `afterUpdate`, `onDelete`). This facilitates decoupled logic for auditing, cache invalidation, and complex validation.

### 6. CLI Tool (`pairity`)
The `pairity` CLI is a first-class citizen of the framework, providing essential developer tools for schema management, code generation, and maintenance. It includes specialized commands for schema evolution (`migrate:dryrun`), availability (`db:check:health`), and state synchronization (`db:check:sync`).

### 7. Identity Map
To maintain data consistency, Pairity uses an Identity Map to track all instantiated DTOs within a single request cycle. This ensures that fetching the same record multiple times returns the same object instance.

### 8. Connection Management
A centralized `DatabaseManager` handles multiple PDO connections, allowing the ORM to communicate with different databases or read/write replicas seamlessly.

### 9. Metadata Caching
To optimize performance, Pairity caches the processed YAML schema and table metadata using a PSR-16 cache. This minimizes filesystem I/O and YAML parsing during runtime.

### 10. Data Seeding & Factories
Pairity provides a robust system for seeding the database with initial data and generating complex DTO states via Factories, facilitating both development and testing.

### 11. Query Logging & Profiling
A centralized logging mechanism to capture all executed SQL queries, their execution time, and bound parameters. This is essential for debugging, performance optimization, and security audits.

### 12. Standardized Pagination
Pairity provides a dedicated `Paginator` object for paginated results. This object encapsulates the DTO collection along with metadata like total records, current page, and navigation helpers, ensuring a consistent API response.

### 13. Structured Exception Hierarchy
Pairity uses a custom exception hierarchy to provide clear, actionable error feedback. This avoids leaking raw database driver exceptions and allows developers to handle specific scenarios (e.g., `RecordNotFoundException`, `QueryException`) gracefully.

### 14. Localization (i18n)
To support global development, Pairity includes a localization layer. Exception messages and CLI output are translated based on the environment configuration (`PAIRITY_LOCALE`), facilitating better accessibility and debugging for developers across different regions.

### 15. DTO Serialization & Transformation
Pairity DTOs provide built-in support for transformation into array or JSON formats, making them ideal for use in modern APIs. This includes the ability to define "hidden" fields (e.g., sensitive data) that are excluded by default, and "exposed" fields that are included during serialization.

### 16. Concurrency Control (Locking)
To handle high-concurrency environments, Pairity provides both Optimistic and Pessimistic locking.
- **Optimistic Locking**: Automatic versioning via a `version` column.
- **Pessimistic Locking**: Support for Shared (Read) and Exclusive (Write) locks via the Query Builder (`sharedLock()`, `lockForUpdate()`).

### 17. Multi-Tenancy & Data Isolation
Pairity supports enterprise-grade multi-tenancy through automatic scoping. By defining a tenant identifier in the YAML schema, the ORM automatically injects the necessary filters into all queries and inserts, ensuring strict data isolation between tenants with zero developer overhead.

### 18. Performance Optimization (Hydrators & Ghost Objects)
To achieve maximum performance, Pairity employs advanced optimization techniques:
- **Pre-Generated Hydrators**: Code-generated classes specialized for populating DTOs, bypassing slow reflection-based instantiation.
- **Lazy Loading via Ghost Objects**: Utilizing the Proxy pattern to instantiate DTOs for relations that only trigger a database query when a property is accessed.

### 19. Custom Extension & Interceptors
Developers can extend the ORM's core behavior through:
- **Custom Cast Types**: Defining reusable casting logic for unique data types (e.g., encryption, spatial data).
- **Database Interceptors (Middleware)**: Hooking into the `DatabaseManager` to intercept and modify raw PDO calls for auditing, security, or custom logging.
- **Virtual Properties**: Defining computed DTO attributes in YAML that behave as first-class fields.

### 20. Data Migrations & Procedural Transforms
Beyond schema changes, Pairity supports procedural data migrations. These are PHP-based scripts used to transform or move data using the ORM's business logic, ensuring complex data transitions are handled safely within the application's domain layer.

### 21. Polymorphic Relationships
Pairity allows a model to belong to more than one other type of model on a single association. This is implemented via `morphTo`, `morphOne`, and `morphMany`, allowing for flexible data structures like shared comment or attachment systems.

### 22. Table Inheritance Mapping
To support domain-driven design, Pairity provides two primary inheritance patterns:
- **Single Table Inheritance (STI)**: Mapping multiple DTO types to a single database table using a discriminator column.
- **Class Table Inheritance (CTI)**: Mapping a class hierarchy across multiple tables, with a base table for common attributes and specialized tables for extended data.

### 23. Auditing & Change Tracking
Pairity includes a built-in auditing system that automatically tracks changes to DTOs. When enabled in the YAML schema, the ORM records the previous and current values of attributes, the timestamp, and the user responsible for the change, facilitating compliance and security auditing.

### 24. Async & Parallel Query Execution
For high-performance applications using PHP Fibers or asynchronous runtimes (e.g., Swoole, RoadRunner), Pairity supports dispatching multiple independent queries in parallel. This maximizes database throughput and reduces total response time for data-heavy operations.

### 25. Attribute Encryption
Pairity provides native support for transparent attribute encryption. Sensitive data (PII) can be marked as `encrypted` in the YAML schema, and the ORM will automatically handle AES-256 encryption/decryption before storage and after retrieval, ensuring data-at-rest security.

### 26. Seed Squashing
To facilitate rapid environment setup, Pairity supports squashing individual seed files into a single, optimized baseline seed. This reduces the overhead of executing hundreds of separate seeder classes and provides a clean starting point for new development or staging environments.

## CLI Actions
List of actions that the `pairity` tool can perform:
- `db:check:health`: Verify database connection health and heartbeat.
- `db:check:sync`: Verify synchronization of manual migration files and seed files (excludes YAML to DB direct).
- `db:seed`: Seed the database with records.
- `db:seed:squash`: Squash all individual seed files into a single baseline seed for new environments.
- `make:factory`: Create a new Factory class for a model.
- `make:migration`: Create a manual migration file for custom SQL or data changes.
- `make:model`: Generate DTO and DAO classes from YAML schema definitions.
- `make:seeder`: Create a new Seeder class.
- `make:yaml:fromdb`: Reverse-engineer an existing database to generate YAML schema files.
- `make:yaml:snapshot`: Export the current YAML source of truth into a single SQL or PHP baseline snapshot.
- `migrate`: Apply pending schema changes defined in YAML files.
- `migrate:dryrun`: Preview all changes that would be run with `migrate` (YAML to DB direct only).
- `migrate:rollback`: Roll back the last database migration.
- `migration:data`: Execute procedural PHP data migrations.

# Milestones

## Table of Contents
- [x] Milestone 1: Planning & Specification
- [x] Milestone 2: CLI Infrastructure & Service Container
- [x] Milestone 3: Error Handling & Localization
- [x] Milestone 4: Connection Management & Drivers
- [x] Milestone 5: Advanced Database Features
- [x] Milestone 6: YAML Schema & Fluent Builder
- [x] Milestone 7: Metadata Management & Caching
- [x] Milestone 8: DTO/DAO Code Generation
- [x] Milestone 9: Performance & State Management
- [x] Milestone 10: Basic Query Builder & CRUD
- [x] Milestone 11: Relationships & Eager Loading
- [x] Milestone 12: Unit of Work & Concurrency
- [x] Milestone 13: Lifecycle Events & Auditing
- [x] Milestone 14: Enterprise Features & Tooling
- [x] Milestone 15: Project Clean Up

---

## Milestone 1: Planning & Specification
- [x] Define Core Concepts and Architecture in SPECS.md
- [x] Establish CLI Tool as a first-class citizen
- [x] Minimal composer.json setup
- [x] Project cleanup and reset to PLANNING status

## Milestone 2: CLI Infrastructure & Service Container
- [x] Implement basic CLI structure (`bin/pairity`)
- [x] Define internal command registration system
- [x] Implement Service Container / Dependency Injection base
- [x] Verify binary exposure via Composer (`bin` entry in `composer.json`)

## Milestone 3: Error Handling & Localization
- [x] Implement Exception Hierarchy
- [x] Implement i18n Translation Layer for CLI and Exceptions

## Milestone 4: Connection Management & Drivers
- [x] Implement `DatabaseManager`
- [x] Implement Driver abstractions (MySQL, Postgres, SQLite, etc.)
- [x] Support for multiple named connections
- [x] Implement Connection Heartbeat and Health Check logic

## Milestone 5: Advanced Database Features
- [x] Implement Read/Write splitting logic
- [x] Implement Manual Transaction and Savepoint support
- [x] Implement Database Interceptors (Middleware)

## Milestone 6: YAML Schema & Fluent Builder
- [x] Define YAML Schema format for table definitions (including Tenancy, Prefix, Inheritance, and Morph)
- [x] Implement Fluent Schema Builder API
- [x] Implement YAML parser and validator (supporting Enum casting, attribute visibility, Custom Types, and Encrypted Attributes)
- [x] Implement JSON Schema for YAML autocompletion (Opportunity Radar)
- [x] Implement Schema Linting CLI command (Opportunity Radar)
- [x] Implement Type Mapping Registry (Opportunity Radar)

## Milestone 7: Metadata Management & Caching
- [x] Implement Metadata Cache (PSR-16 integration)
- [x] Support for Database View mapping
- [x] Implement Schema Snapshotting (`make:yaml:snapshot`) and Blueprint exporting

## Milestone 8: DTO/DAO Code Generation
- [x] Implement Code Generator for DTOs (supporting serialization, visibility, Virtual Properties, Inheritance, and Encrypted Attributes)
- [x] Implement Code Generator for DAOs
- [x] Implement basic CRUD and Mass Operations (Insert, Update, Delete) in DAOs

## Milestone 9: Performance & State Management
- [x] Implement Pre-Generated Hydrators for performance
- [x] Implement Identity Map pattern for DTO tracking
- [x] Implement Lazy Loading via Ghost Objects (Proxies)

## Milestone 10: Basic Query Builder & CRUD
- [x] Implement fluent Query Builder
- [x] Implement Subquery support in FROM and WHERE IN (Advanced Features)
- [x] Implement Advanced Subquery Features (Joins, Existence Checks, Selects)
- [x] Implement Query Result Normalization (Consistent object syntax for raw results)
- [x] Implement Raw SQL Expressions support
- [x] Implement Driver-Specific Hints and Options
- [x] Implement Query Logging and Profiling
- [x] Implement Standardized Paginator (Offset and Cursor)
- [x] Safety Check: Prevent unconstrained Mass Update/Delete via configuration

## Milestone 11: Relationships & Eager Loading
- [x] Implement Nested, Constrained, and Polymorphic Eager Loading
- [x] Support for Polymorphic relations in DAOs

## Milestone 12: Unit of Work & Concurrency
- [x] Implement Unit of Work for transactional tracking
- [x] Implement Optimistic Locking (versioning)
- [x] Support for Pessimistic Locking (`sharedLock`, `lockForUpdate`)
- [x] Implement Upsert logic

## Milestone 13: Lifecycle Events & Auditing
- [x] Implement Event Dispatcher and lifecycle hooks
- [x] Implement Auditing System

## Milestone 14: Enterprise Features & Tooling
- [x] Implement Automatic Multi-Tenancy scoping
- [x] Implement Data Seeding system
- [x] Implement Procedural Data Migration System (`migration:data`)
- [x] Implement Model Factories
- [x] Implement Seed Squashing (`db:seed:squash`)
- [x] Implement manual migration support
- [x] Implement Schema Introspection (`make:yaml:fromdb`)
- [x] Implement Database Health Check commands (`db:check:health`, `db:check:sync`)
- [x] Implement Query Result Caching
- [x] Implement Async/Parallel Query Execution logic
- [x] Implement Set Operations (Unions, Intersect, Except)

## Milestone 15: Project Clean Up
- [x] Update all thrown exceptions in codebase to use the i18n localization for exception messages
- [x] Exception Context Standardization: Ensure all exceptions leverage the context array in PairityException to pass structured data
- [x] Dead Code Elimination: Identify and remove any unused traits or helper methods
- [x] Create Translation files for Spanish (es.php), French (fr.php), German (de.php), and Italian (it.php)
- [x] Implement Task Priority rule [R21] and Definition of Done guidelines
- [x] Update README to be a normal project type README. Make sure all necessary Features, Installation examples, Usage examples (both DAO/DTO and Query Builder), and anything else typically found in a project README are present.


# Milestones

## Table of Contents
1. [Milestone 1: Core DTO/DAO & Persistence](#milestone-1-core-dtodao--persistence)
2. [Milestone 2: Basic Relations & Eager Loading](#milestone-2-basic-relations--eager-loading)
3. [Milestone 3: Attribute Accessors/Mutators & Custom Casters](#milestone-3-attribute-accessorsmutators--custom-casters)
4. [Milestone 4: Unit of Work & Identity Map](#milestone-4-unit-of-work--identity-map)
5. [Milestone 5: Pagination & Query Scopes](#milestone-5-pagination--query-scopes)
6. [Milestone 6: Event System](#milestone-6-event-system)
7. [Milestone 7: Performance Knobs](#milestone-7-performance-knobs)
8. [Milestone 8: Road Ahead](#milestone-8-road-ahead) [x]

---

## Milestone 1: Core DTO/DAO & Persistence
- [x] Basic AbstractDto and AbstractDao
- [x] CRUD operations (Insert, Update, Delete, Find)
- [x] Schema metadata (Casts, Timestamps, Soft Deletes)
- [x] Dynamic DAO methods
- [x] Basic SQL and SQLite support

## Milestone 2: Basic Relations & Eager Loading
- [x] `hasOne`, `hasMany`, `belongsTo`
- [x] Batched eager loading (IN lookup)
- [x] Join-based eager loading (SQL)
- [x] Nested eager loading (dot notation)
- [x] `belongsToMany` and Pivot Helpers

## Milestone 3: Attribute Accessors/Mutators & Custom Casters
- [x] DTO accessors and mutators
- [x] Pluggable per-column custom casters
- [x] Casting integration with hydration and storage

## Milestone 4: Unit of Work & Identity Map
- [x] Identity Map implementation
- [x] Deferred mutations (updates/deletes)
- [x] Transactional/Atomic commits
- [x] Relation-aware delete cascades
- [x] Optimistic Locking (SQL & Mongo)

## Milestone 5: Pagination & Query Scopes
- [x] `paginate` and `simplePaginate` for SQL and Mongo
- [x] Ad-hoc and Named Query Scopes

## Milestone 6: Event System
- [x] Dispatcher and Subscriber interfaces
- [x] DAO lifecycle events
- [x] Unit of Work commit events

## Milestone 7: Performance Knobs
- [x] PDO prepared-statement cache
- [x] Query timing hooks
- [x] Eager loader IN-batching
- [x] Metadata memoization

## Milestone 8: Road Ahead
- [x] Broader Schema Builder ALTER coverage
- [x] More dialect nuances for SQL Server/Oracle
- [x] Enhanced CLI commands
- [x] Caching layer
- [ ] Production-ready MongoDB adapter refinements
- [ ] Documentation and expanded examples

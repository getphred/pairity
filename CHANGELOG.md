### Changelog

All notable changes to this project will be documented in this file.

#### [0.1.1] - 2026-01-07

##### Changed
- **MongoDB Downgrade**: Stepped back MongoDB support from `mongodb/mongodb` ^2.0 to ^1.20 for broader compatibility with older `ext-mongodb` environments.
- **Refactoring**: Simplified `normalizeFilter` in `MongoClientConnection` for better maintainability.
- **Features**: Added `count()` method to `MongoConnectionInterface` and implementations.

#### [0.1.0] - 2026-01-06

##### Added
- **Caching Layer**: Integrated PSR-16 (Simple Cache) support into `AbstractDao` and `AbstractMongoDao`.
    - Automated cache invalidation on write operations.
    - Identity Map synchronization for cached objects.
    - Customizable TTL and prefix per DAO.
- **CLI Enhancements**:
    - Extracted CLI logic into dedicated Command classes in `Pairity\Console`.
    - Added `--pretend` flag to `migrate`, `rollback`, and `reset` commands for dry-run support.
    - Added `--template` flag to `make:migration` for custom migration boilerplate.
- **MongoDB Refinements**:
    - Production-ready `MongoClientConnection` wrapping `mongodb/mongodb` ^2.0.
    - Integrated caching into `AbstractMongoDao`.
    - Improved `_id` normalization and BSON type handling.
- **Improved Testing**: Added `PretendTest`, `MigrationGeneratorTest`, and `CachingTest`.

##### Changed
- Updated `AbstractDto` to support `\Serializable` and PHP 8.1+ `__serialize()`/`__unserialize()` for better cache persistence.
- Refactored `bin/pairity` to use the new Console Command structure.

#### [0.0.1] - 2025-12-11
- Initial development version with core ORM, Relations, Migrations, and basic MongoDB support.

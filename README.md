# Pairity

A partitioned‑model PHP ORM (DTO/DAO) with Query Builder, relations, raw SQL helpers, and a portable migrations + schema builder.

![CI](https://github.com/getphred/pairity/actions/workflows/ci.yml/badge.svg)
![Packagist](https://img.shields.io/packagist/v/getphred/pairity.svg)

## Installation

- **Requirements**: PHP >= 8.2, PDO extension for your database(s)
- **Install via Composer**:

```bash
composer require getphred/pairity
```

After install, you can use the CLI at `vendor/bin/pairity`.

## Testing

This project uses PHPUnit 10. The default test suite excludes MongoDB integration tests by default for portability.

- **Install dev dependencies**:
```bash
composer install
```

- **Run the default suite** (SQLite + unit tests; Mongo tests excluded by default):
```bash
vendor/bin/phpunit
```

- **Run MongoDB integration tests** (requires `ext-mongodb >= 1.20` and a reachable server):
```bash
MONGO_HOST=127.0.0.1 MONGO_PORT=27017 vendor/bin/phpunit --group mongo-integration
```

## Quick Start

Minimal example with SQLite and a simple `users` DAO/DTO.

```php
use Pairity\Database\ConnectionManager;
use Pairity\Model\AbstractDto;
use Pairity\Model\AbstractDao;

// 1) Connect
$conn = ConnectionManager::make([
    'driver' => 'sqlite',
    'path'   => __DIR__ . '/db.sqlite',
]);

// 2) Define DTO + DAO
class UserDto extends AbstractDto {}
class UserDao extends AbstractDao {
    public function getTable(): string { return 'users'; }
    protected function dtoClass(): string { return UserDto::class; }
}

// 3) CRUD
$dao = new UserDao($conn);
$created = $dao->insert(['email' => 'a@b.com', 'name' => 'Alice']);
$user = $dao->findById($created->id);
```

For more detailed usage and technical specifications, see [SPECS.md](SPECS.md).

## Documentation
- [Specifications](SPECS.md)
- [Milestones & Roadmap](MILESTONES.md)
- [Examples](examples/)

## Contributing

This is an early foundation. Contributions, discussions, and design proposals are welcome. Please open an issue to coordinate larger features.

## License

MIT
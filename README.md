# Pairity ORM

[![Latest Version on Packagist](https://img.shields.io/packagist/v/getphred/pairity.svg?style=flat-square)](https://packagist.org/packages/getphred/pairity)
[![Total Downloads](https://img.shields.io/packagist/dt/getphred/pairity.svg?style=flat-square)](https://packagist.org/packages/getphred/pairity)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Pairity is a high-performance, partitioned-model PHP ORM that strictly separates data representation (DTO) from persistence logic (DAO). It provides a fluent Query Builder, robust relationship management, and enterprise-grade features like automatic multi-tenancy, auditing, and transparent attribute encryption.

## Key Features

- **Partitioned Model**: Clean separation between DTOs (Data) and DAOs (Persistence).
- **YAML-Driven Schema**: Define your tables in YAML; generate migrations and models automatically.
- **Fluent Query Builder**: Database-agnostic query construction with support for subqueries, joins, and set operations.
- **Relationships & Eager Loading**: Efficiently handle BelongsTo, HasOne, HasMany, and Polymorphic relations with N+1 prevention.
- **Unit of Work**: Coordinate atomic updates across multiple connections with centralized transaction management.
- **Enterprise Features**:
    - **Automatic Multi-Tenancy**: Transparent data isolation via tenant scoping.
    - **Auditing**: Automatic change tracking for sensitive models.
    - **Concurrency Control**: Built-in Optimistic and Pessimistic locking.
    - **Attribute Encryption**: Transparent AES-256 encryption for PII data.
- **Developer Tooling**: First-class CLI (`pairity`) for code generation, migrations, seeding, and health checks.
- **Internationalization (i18n)**: Localized exception messages and CLI output (EN, ES, FR, DE, IT).

## Installation

Install Pairity via Composer:

```bash
composer require getphred/pairity
```

Initialize the project structure (creates `schema/`, `src/Models/`, etc.):

```bash
vendor/bin/pairity init
```

## Quick Start

### 1. Define your Schema

Create a YAML file in `schema/users.yaml` (Note: the `schema/` directory is automatically created by the `init` command):

```yaml
columns:
  id:
    type: bigIncrements
  email:
    type: string
    unique: true
  password:
    type: string
    encrypted: true
  active:
    type: boolean
    default: true

relations:
  posts:
    type: hasMany
    target: posts
```

### 2. Generate Models

```bash
vendor/bin/pairity make:model
```

### 3. Run Migrations

```bash
vendor/bin/pairity migrate
```

### 4. Usage

#### Basic CRUD

```php
use App\Models\DTO\UsersDTO;
use App\Models\DAO\UsersDAO;

$userDao = $container->get(UsersDAO::class);

// Create
$user = new UsersDTO(['email' => 'jane@example.com', 'password' => 'secret']);
$userDao->save($user);

// Read
$user = $userDao->find(1);
echo $user->email;

// Update
$user->setAttribute('active', false);
$userDao->save($user);
```

#### Query Builder

```php
$users = $userDao->query()
    ->where('active', true)
    ->whereIn('role', ['admin', 'editor'])
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

#### Eager Loading

```php
$users = $userDao->query()
    ->with('posts.comments')
    ->get();

foreach ($users as $user) {
    foreach ($user->posts as $post) {
        // Posts are already loaded!
    }
}
```

## Documentation

For detailed documentation, please refer to the [SPECS.md](SPECS.md) file.

## Testing

Run the test suite using PHPUnit:

```bash
vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

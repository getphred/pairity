<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

final class PostgresSmokeTest extends TestCase
{
    private function pgConfig(): array
    {
        $host = getenv('POSTGRES_HOST') ?: null;
        if (!$host) {
            $this->markTestSkipped('POSTGRES_HOST not set; skipping Postgres smoke test');
        }
        return [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => (int)(getenv('POSTGRES_PORT') ?: 5432),
            'database' => getenv('POSTGRES_DB') ?: 'pairity',
            'username' => getenv('POSTGRES_USER') ?: 'postgres',
            'password' => getenv('POSTGRES_PASS') ?: 'postgres',
        ];
    }

    public function testCreateAlterDropCycle(): void
    {
        $cfg = $this->pgConfig();
        $conn = ConnectionManager::make($cfg);
        $schema = SchemaManager::forConnection($conn);

        $suffix = substr(sha1((string)microtime(true)), 0, 6);
        $table = 'pg_smoke_' . $suffix;

        // Create
        $schema->create($table, function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 100);
        });

        $rows = $conn->query('SELECT tablename FROM pg_tables WHERE tablename = :t', ['t' => $table]);
        $this->assertNotEmpty($rows, 'Table should be created');

        // Alter add column
        $schema->table($table, function (Blueprint $t) {
            $t->integer('qty');
        });
        $cols = $conn->query('SELECT column_name FROM information_schema.columns WHERE table_name = :t', ['t' => $table]);
        $names = array_map(fn($r) => $r['column_name'] ?? '', $cols);
        $this->assertContains('qty', $names);

        // Drop
        $schema->drop($table);
        $rows = $conn->query('SELECT tablename FROM pg_tables WHERE tablename = :t', ['t' => $table]);
        $this->assertEmpty($rows, 'Table should be dropped');
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

final class MysqlSmokeTest extends TestCase
{
    private function mysqlConfig(): array
    {
        $host = getenv('MYSQL_HOST') ?: null;
        if (!$host) {
            $this->markTestSkipped('MYSQL_HOST not set; skipping MySQL smoke test');
        }
        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
            'database' => getenv('MYSQL_DB') ?: 'pairity',
            'username' => getenv('MYSQL_USER') ?: 'root',
            'password' => getenv('MYSQL_PASS') ?: 'root',
            'charset' => 'utf8mb4',
        ];
    }

    public function testCreateAndDropTable(): void
    {
        $cfg = $this->mysqlConfig();
        $conn = ConnectionManager::make($cfg);
        $schema = SchemaManager::forConnection($conn);

        $table = 'pairity_smoke_' . substr(sha1((string)microtime(true)), 0, 6);

        $schema->create($table, function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 50);
        });

        $rows = $conn->query('SHOW TABLES LIKE :t', ['t' => $table]);
        $this->assertNotEmpty($rows, 'Table should be created');

        // Alter add column
        $schema->table($table, function (Blueprint $t) {
            $t->integer('qty');
        });

        $cols = $conn->query('SHOW COLUMNS FROM `' . $table . '`');
        $names = array_map(fn($r) => $r['Field'] ?? $r['field'] ?? $r['COLUMN_NAME'] ?? '', $cols);
        $this->assertContains('qty', $names);

        // Drop
        $schema->drop($table);
        $rows = $conn->query('SHOW TABLES LIKE :t', ['t' => $table]);
        $this->assertEmpty($rows, 'Table should be dropped');
    }
}

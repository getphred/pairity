<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\Database\ConnectionManager;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

final class SchemaBuilderSqliteTest extends TestCase
{
    public function testCreateAlterDropCycle(): void
    {
        $conn = ConnectionManager::make([
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);

        $schema = SchemaManager::forConnection($conn);

        // Create table
        $schema->create('widgets', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 100)->nullable();
            $t->integer('qty');
            $t->unique(['name'], 'widgets_name_uk');
            $t->index(['qty'], 'widgets_qty_idx');
        });

        // Verify table exists
        $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='widgets'");
        $this->assertNotEmpty($tables, 'widgets table should exist');

        // Alter: add column
        $schema->table('widgets', function (Blueprint $t) {
            $t->string('desc', 255)->nullable();
        });

        $cols = $conn->query("PRAGMA table_info('widgets')");
        $colNames = array_map(fn($r) => $r['name'], $cols);
        $this->assertContains('desc', $colNames);

        // Alter: rename column qty -> quantity
        $schema->table('widgets', function (Blueprint $t) {
            $t->renameColumn('qty', 'quantity');
        });
        $cols = $conn->query("PRAGMA table_info('widgets')");
        $colNames = array_map(fn($r) => $r['name'], $cols);
        $this->assertContains('quantity', $colNames);
        $this->assertNotContains('qty', $colNames);

        // Alter: drop column desc
        $schema->table('widgets', function (Blueprint $t) {
            $t->dropColumn('desc');
        });
        $cols = $conn->query("PRAGMA table_info('widgets')");
        $colNames = array_map(fn($r) => $r['name'], $cols);
        $this->assertNotContains('desc', $colNames);

        // Rename table
        $schema->table('widgets', function (Blueprint $t) {
            $t->rename('widgets_new');
        });
        $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='widgets_new'");
        $this->assertNotEmpty($tables, 'widgets_new table should exist after rename');

        // Drop
        $schema->drop('widgets_new');
        $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='widgets_new'");
        $this->assertEmpty($tables, 'widgets_new table should be dropped');
    }
}

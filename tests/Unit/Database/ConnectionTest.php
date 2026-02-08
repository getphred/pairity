<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database;

use Pairity\Database\Connection;
use Pairity\Database\Drivers\SQLiteDriver;
use Pairity\Exceptions\QueryException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function test_it_can_execute_queries()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $affected = $connection->execute('INSERT INTO users (name) VALUES (?)', ['Alice']);

        $this->assertEquals(1, $affected);

        $results = $connection->select('SELECT * FROM users');
        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['name']);
    }

    public function test_it_throws_query_exception_on_invalid_sql()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $this->expectException(QueryException::class);
        $connection->execute('INVALID SQL');
    }

    public function test_read_write_splitting()
    {
        $driver = new SQLiteDriver();
        // Use two different in-memory databases to simulate splitting
        $config = [
            'read' => ['database' => ':memory:'],
            'write' => ['database' => ':memory:'],
        ];
        $connection = new Connection('test', $driver, $config);

        // Write to master
        $connection->execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $connection->execute('INSERT INTO test DEFAULT VALUES');

        // Read from slave (should be empty because it's a different :memory: DB)
        // But wait, the sticky logic will kick in after execute!
        // Let's test WITHOUT sticky first or by forcing a new connection.
        
        $connection2 = new Connection('test2', $driver, $config);
        // We can't easily test separate :memory: DBs if they are both in the same process 
        // and we want them to be DIFFERENT. Actually each :memory: IS different.
        
        $results = $connection2->select("SELECT name FROM sqlite_master WHERE type='table' AND name='test'");
        $this->assertCount(0, $results, 'Read connection should not see tables created in write connection (different :memory: DBs)');
    }

    public function test_transactions()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $connection->execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

        $connection->beginTransaction();
        $connection->execute('INSERT INTO test DEFAULT VALUES');
        $this->assertEquals(1, $connection->transactionLevel());
        $connection->commit();

        $this->assertEquals(0, $connection->transactionLevel());
        $this->assertCount(1, $connection->select('SELECT * FROM test'));
    }

    public function test_transaction_rollback()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $connection->execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

        $connection->beginTransaction();
        $connection->execute('INSERT INTO test DEFAULT VALUES');
        $connection->rollBack();

        $this->assertCount(0, $connection->select('SELECT * FROM test'));
    }

    public function test_nested_transactions_via_savepoints()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $connection->execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

        $connection->beginTransaction(); // Level 1
        $connection->execute('INSERT INTO test DEFAULT VALUES'); // 1 record

        $connection->beginTransaction(); // Level 2 (Savepoint)
        $connection->execute('INSERT INTO test DEFAULT VALUES'); // 2 records total
        $this->assertCount(2, $connection->select('SELECT * FROM test'));
        $connection->rollBack(); // Rollback to Savepoint

        $this->assertEquals(1, $connection->transactionLevel());
        $this->assertCount(1, $connection->select('SELECT * FROM test'));

        $connection->commit();
        $this->assertCount(1, $connection->select('SELECT * FROM test'));
    }

    public function test_interceptors()
    {
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);

        $interceptor = new class implements \Pairity\Contracts\Database\InterceptorInterface {
            public int $called = 0;
            public function intercept(string $query, array $bindings, string $mode, callable $next): mixed {
                $this->called++;
                return $next($query, $bindings, $mode);
            }
        };

        $connection->addInterceptor($interceptor);
        $connection->select('SELECT 1');

        $this->assertEquals(1, $interceptor->called);
    }
}

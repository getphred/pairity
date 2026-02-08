<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Interceptors;

use Pairity\Database\Interceptors\QueryLogger;
use Pairity\Database\Connection;
use Pairity\Database\Drivers\SQLiteDriver;
use PHPUnit\Framework\TestCase;

class QueryLoggerTest extends TestCase
{
    public function test_it_logs_queries()
    {
        $logger = new QueryLogger();
        $driver = new SQLiteDriver();
        $connection = new Connection('test', $driver, ['database' => ':memory:']);
        $connection->addInterceptor($logger);

        $connection->select('SELECT 1');
        $connection->execute('CREATE TABLE test (id INTEGER)');
        $connection->execute('INSERT INTO test (id) VALUES (?)', [123]);

        $logs = $logger->getLogs();

        $this->assertCount(3, $logs);
        $this->assertEquals('SELECT 1', $logs[0]['query']);
        $this->assertEquals('read', $logs[0]['mode']);
        $this->assertIsFloat($logs[0]['time']);

        $this->assertEquals('INSERT INTO test (id) VALUES (?)', $logs[2]['query']);
        $this->assertEquals([123], $logs[2]['bindings']);
        $this->assertEquals('write', $logs[2]['mode']);
    }
}

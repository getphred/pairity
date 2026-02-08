<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database;

use Pairity\Database\DatabaseManager;
use Pairity\Database\Connection;
use Pairity\Database\Drivers\SQLiteDriver;
use PHPUnit\Framework\TestCase;

class DatabaseManagerTest extends TestCase
{
    public function test_it_resolves_default_connection()
    {
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];

        $manager = new DatabaseManager($config);
        $connection = $manager->connection();

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals('sqlite', $connection->getName());
        $this->assertInstanceOf(SQLiteDriver::class, $connection->getDriver());
    }

    public function test_it_resolves_named_connection()
    {
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
                'other' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];

        $manager = new DatabaseManager($config);
        $connection = $manager->connection('other');

        $this->assertEquals('other', $connection->getName());
    }

    public function test_it_caches_connections()
    {
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];

        $manager = new DatabaseManager($config);
        $connection1 = $manager->connection();
        $connection2 = $manager->connection();

        $this->assertSame($connection1, $connection2);
    }

    public function test_it_can_reconnect()
    {
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];

        $manager = new DatabaseManager($config);
        $connection1 = $manager->connection();
        $connection2 = $manager->reconnect();

        $this->assertNotSame($connection1, $connection2);
    }

    public function test_it_throws_exception_for_unsupported_driver()
    {
        $config = [
            'default' => 'invalid',
            'connections' => [
                'invalid' => [
                    'driver' => 'unsupported',
                ],
            ],
        ];

        $manager = new DatabaseManager($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database driver [unsupported] not supported.');

        $manager->connection();
    }
}

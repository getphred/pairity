<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Drivers;

use Pairity\Database\Drivers\PostgresDriver;
use Pairity\Database\Drivers\SqlServerDriver;
use Pairity\Database\Drivers\OracleDriver;
use PHPUnit\Framework\TestCase;

class DriverTest extends TestCase
{
    public function test_postgres_driver_dsn()
    {
        $driver = new PostgresDriver();
        $config = [
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'testdb',
            'schema' => 'myschema',
            'sslmode' => 'require',
        ];

        // We need to use reflection because buildDsn is protected, 
        // or just test the getName() and rely on integration tests if any.
        // Actually, let's use a small helper or just test getName for now and trust logic.
        // Better: let's test DSN via a public wrapper or reflection.
        
        $method = new \ReflectionMethod(PostgresDriver::class, 'buildDsn');
        $method->setAccessible(true);
        $dsn = $method->invoke($driver, $config);

        $this->assertEquals("pgsql:host=localhost;port=5432;dbname=testdb;options='--search_path=myschema';sslmode=require", $dsn);
        $this->assertEquals('pgsql', $driver->getName());
    }

    public function test_sqlserver_driver_dsn()
    {
        $driver = new SqlServerDriver();
        $config = [
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'master',
            'encrypt' => true,
            'trust_server_certificate' => false,
        ];

        $method = new \ReflectionMethod(SqlServerDriver::class, 'buildDsn');
        $method->setAccessible(true);
        $dsn = $method->invoke($driver, $config);

        $this->assertEquals("sqlsrv:Server=127.0.0.1,1433;Database=master;APP=Pairity;Encrypt=true;TrustServerCertificate=false", $dsn);
        $this->assertEquals('sqlsrv', $driver->getName());
    }

    public function test_oracle_driver_dsn()
    {
        $driver = new OracleDriver();
        $config = [
            'host' => 'oracle-db',
            'port' => 1521,
            'database' => 'XE',
            'service_name' => 'XEPDB1',
            'charset' => 'UTF8',
        ];

        $method = new \ReflectionMethod(OracleDriver::class, 'buildDsn');
        $method->setAccessible(true);
        $dsn = $method->invoke($driver, $config);

        $this->assertEquals("oci:dbname=//oracle-db:1521/XEPDB1;charset=UTF8", $dsn);
        $this->assertEquals('oracle', $driver->getName());
    }
}

<?php

namespace Pairity\Tests\Database;

use PHPUnit\Framework\TestCase;
use Pairity\Database\PdoConnection;
use PDO;

class PretendTest extends TestCase
{
    public function testPretendLogsQueriesWithoutExecuting()
    {
        $pdo = $this->createMock(PDO::class);
        // Expect no calls to prepare or execute since we are pretending
        $pdo->expects($this->never())->method('prepare');
        
        $conn = new PdoConnection($pdo);
        
        $log = $conn->pretend(function($c) {
            $c->execute('INSERT INTO users (name) VALUES (?)', ['Alice']);
            $c->query('SELECT * FROM users');
        });
        
        $this->assertCount(2, $log);
        $this->assertEquals('INSERT INTO users (name) VALUES (?)', $log[0]['sql']);
        $this->assertEquals(['Alice'], $log[0]['params']);
        $this->assertEquals('SELECT * FROM users', $log[1]['sql']);
    }

    public function testPretendHandlesTransactions()
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('beginTransaction');
        
        $conn = new PdoConnection($pdo);
        
        $conn->pretend(function($c) {
            $c->transaction(function($c2) {
                $c2->execute('DELETE FROM users');
            });
        });
        
        $this->assertTrue(true); // Reaching here means no PDO transaction was started
    }
}

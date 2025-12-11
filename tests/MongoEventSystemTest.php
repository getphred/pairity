<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\AbstractMongoDao;
use Pairity\Model\AbstractDto;
use Pairity\Events\Events;

/**
 * @group mongo-integration
 */
final class MongoEventSystemTest extends TestCase
{
    private function hasMongoExt(): bool { return \extension_loaded('mongodb'); }

    public function testDaoEventsFireOnCrud(): void
    {
        if (!$this->hasMongoExt()) { $this->markTestSkipped('ext-mongodb not loaded'); }

        // Connect (skip if server unavailable)
        try {
            $conn = MongoConnectionManager::make([
                'host' => \getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(\getenv('MONGO_PORT') ?: 27017),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }
        // Ping server to ensure availability
        try {
            $conn->getClient()->selectDatabase('admin')->command(['ping' => 1]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo not available: ' . $e->getMessage());
        }

        $Dto = new class([]) extends AbstractDto {};
        $dtoClass = \get_class($Dto);

        $Dao = new class($conn, $dtoClass) extends AbstractMongoDao {
            private string $dto; public function __construct($c,string $d){ parent::__construct($c); $this->dto=$d; }
            protected function collection(): string { return 'pairity_test.events_users'; }
            protected function dtoClass(): string { return $this->dto; }
        };

        $dao = new $Dao($conn, $dtoClass);

        // Clean
        foreach ($dao->findAllBy([]) as $doc) { $id = (string)($doc->toArray(false)['_id'] ?? ''); if ($id) { $dao->deleteById($id); } }

        $beforeInsert = null; $afterInsert = false; $afterUpdate = false; $afterDelete = 0; $afterFind = 0;
        Events::dispatcher()->clear();
        Events::dispatcher()->listen('dao.beforeInsert', function(array &$p) use (&$beforeInsert){ if (($p['collection'] ?? '') === 'pairity_test.events_users'){ $p['data']['tag'] = 'x'; $beforeInsert = $p['data']; }});
        Events::dispatcher()->listen('dao.afterInsert', function(array &$p) use (&$afterInsert){ if (($p['collection'] ?? '') === 'pairity_test.events_users'){ $afterInsert = true; }});
        Events::dispatcher()->listen('dao.afterUpdate', function(array &$p) use (&$afterUpdate){ if (($p['collection'] ?? '') === 'pairity_test.events_users'){ $afterUpdate = true; }});
        Events::dispatcher()->listen('dao.afterDelete', function(array &$p) use (&$afterDelete){ if (($p['collection'] ?? '') === 'pairity_test.events_users'){ $afterDelete += (int)($p['affected'] ?? 0); }});
        Events::dispatcher()->listen('dao.afterFind', function(array &$p) use (&$afterFind){ if (($p['collection'] ?? '') === 'pairity_test.events_users'){ $afterFind += isset($p['dto']) ? (int)!!$p['dto'] : (is_array($p['dtos'] ?? null) ? count($p['dtos']) : 0); }});

        // Insert
        $created = $dao->insert(['email' => 'e@x.com']);
        $this->assertTrue($afterInsert);
        $this->assertSame('x', $created->toArray(false)['tag'] ?? null);

        // Update
        $id = (string)($created->toArray(false)['_id'] ?? '');
        $dao->update($id, ['email' => 'e2@x.com']);
        $this->assertTrue($afterUpdate);

        // Find
        $one = $dao->findById($id);
        $this->assertNotNull($one);
        $this->assertGreaterThanOrEqual(1, $afterFind);

        // Delete
        $aff = $dao->deleteById($id);
        $this->assertSame($aff, $afterDelete);
    }
}

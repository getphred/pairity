<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase;
use Pairity\NoSql\Mongo\MongoConnectionManager;

/**
 * @group mongo-integration
 */
final class MongoAdapterTest extends TestCase
{
    private function hasMongoExt(): bool
    {
        return extension_loaded('mongodb');
    }

    public function testCrudCycle(): void
    {
        if (!$this->hasMongoExt()) {
            $this->markTestSkipped('ext-mongodb not loaded');
        }

        // Attempt connection; skip if server is unavailable
        try {
            $conn = MongoConnectionManager::make([
                'host' => getenv('MONGO_HOST') ?: '127.0.0.1',
                'port' => (int)(getenv('MONGO_PORT') ?: 27017),
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

        $db = 'pairity_test';
        $col = 'widgets';

        // Clean up any leftovers
        try {
            foreach ($conn->find($db, $col, []) as $doc) {
                $conn->deleteOne($db, $col, ['_id' => $doc['_id']]);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Mongo operations unavailable: ' . $e->getMessage());
        }

        // Insert
        $id = $conn->insertOne($db, $col, [
            'name' => 'Widget',
            'qty' => 5,
            'tags' => ['a','b'],
        ]);
        $this->assertNotEmpty($id, 'Inserted _id should be returned');

        // Find by id
        $found = $conn->find($db, $col, ['_id' => $id]);
        $this->assertNotEmpty($found, 'Should find inserted doc');
        $this->assertSame('Widget', $found[0]['name'] ?? null);

        // Update
        $modified = $conn->updateOne($db, $col, ['_id' => $id], ['$set' => ['qty' => 7]]);
        $this->assertGreaterThanOrEqual(0, $modified);
        $after = $conn->find($db, $col, ['_id' => $id]);
        $this->assertSame(7, $after[0]['qty'] ?? null);

        // Aggregate pipeline
        $agg = $conn->aggregate($db, $col, [
            ['$match' => ['qty' => 7]],
            ['$project' => ['name' => 1, 'qty' => 1]],
        ]);
        $this->assertNotEmpty($agg);

        // Delete
        $deleted = $conn->deleteOne($db, $col, ['_id' => $id]);
        $this->assertGreaterThanOrEqual(1, $deleted);
        $remaining = $conn->find($db, $col, ['_id' => $id]);
        $this->assertCount(0, $remaining);
    }
}

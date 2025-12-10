<?php

namespace Pairity\NoSql\Mongo;

use MongoDB\Client;

/**
 * Simple Index manager for MongoDB collections.
 */
final class IndexManager
{
    private MongoConnectionInterface $connection;
    private string $database;
    private string $collection;

    public function __construct(MongoConnectionInterface $connection, string $database, string $collection)
    {
        $this->connection = $connection;
        $this->database = $database;
        $this->collection = $collection;
    }

    /**
     * Ensure index on keys (e.g., ['email' => 1]) with options (e.g., ['unique' => true]).
     * Returns index name.
     * @param array<string,int> $keys
     * @param array<string,mixed> $options
     */
    public function ensureIndex(array $keys, array $options = []): string
    {
        $client = $this->getClient();
        $mgr = $client->selectCollection($this->database, $this->collection)->createIndex($keys, $options);
        return (string)$mgr;
    }

    /** Drop an index by name. */
    public function dropIndex(string $name): void
    {
        $client = $this->getClient();
        $client->selectCollection($this->database, $this->collection)->dropIndex($name);
    }

    /** @return array<int,array<string,mixed>> */
    public function listIndexes(): array
    {
        $client = $this->getClient();
        $it = $client->selectCollection($this->database, $this->collection)->listIndexes();
        $out = [];
        foreach ($it as $ix) {
            $out[] = json_decode(json_encode($ix), true) ?? [];
        }
        return $out;
    }

    private function getClient(): Client
    {
        if ($this->connection instanceof MongoClientConnection) {
            return $this->connection->getClient();
        }
        // Fallback: attempt to reflect getClient()
        if (method_exists($this->connection, 'getClient')) {
            /** @var Client $c */
            $c = $this->connection->getClient();
            return $c;
        }
        throw new \RuntimeException('IndexManager requires MongoClientConnection');
    }
}

<?php

namespace Pairity\NoSql\Mongo;

/**
 * Minimal MongoDB stub. This implementation keeps data in-memory for demo/testing
 * without introducing external dependencies. It mimics a subset of operations.
 * Replace with a real adapter wrapping `mongodb/mongodb` later.
 */
class MongoConnection implements MongoConnectionInterface
{
    /**
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     *        $store[db][collection][] = document
     */
    private array $store = [];

    public function find(string $database, string $collection, array $filter = [], array $options = []): iterable
    {
        $docs = $this->getCollection($database, $collection);
        $result = [];
        foreach ($docs as $doc) {
            if ($this->matches($doc, $filter)) {
                $result[] = $doc;
            }
        }
        return $result;
    }

    public function insertOne(string $database, string $collection, array $document): string
    {
        $document['_id'] = $document['_id'] ?? $this->generateId();
        $this->store[$database][$collection][] = $document;
        return (string)$document['_id'];
    }

    public function updateOne(string $database, string $collection, array $filter, array $update, array $options = []): int
    {
        $docs =& $this->store[$database][$collection];
        if (!is_array($docs)) {
            $docs = [];
        }
        foreach ($docs as &$doc) {
            if ($this->matches($doc, $filter)) {
                // Very naive: support direct field set or $set operator
                if (isset($update['$set']) && is_array($update['$set'])) {
                    foreach ($update['$set'] as $k => $v) {
                        $doc[$k] = $v;
                    }
                } else {
                    foreach ($update as $k => $v) {
                        $doc[$k] = $v;
                    }
                }
                return 1;
            }
        }
        return 0;
    }

    public function deleteOne(string $database, string $collection, array $filter, array $options = []): int
    {
        $docs =& $this->store[$database][$collection];
        if (!is_array($docs)) {
            $docs = [];
        }
        foreach ($docs as $i => $doc) {
            if ($this->matches($doc, $filter)) {
                array_splice($docs, $i, 1);
                return 1;
            }
        }
        return 0;
    }

    public function aggregate(string $database, string $collection, array $pipeline, array $options = []): iterable
    {
        // Stub: no real pipeline support; just return all docs
        return $this->getCollection($database, $collection);
    }

    private function &getCollection(string $database, string $collection): array
    {
        if (!isset($this->store[$database][$collection])) {
            $this->store[$database][$collection] = [];
        }
        return $this->store[$database][$collection];
    }

    /** @param array<string,mixed> $doc @param array<string,mixed> $filter */
    private function matches(array $doc, array $filter): bool
    {
        foreach ($filter as $k => $v) {
            if (!array_key_exists($k, $doc) || $doc[$k] !== $v) {
                return false;
            }
        }
        return true;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(12));
    }
}

<?php

namespace Pairity\NoSql\Mongo;

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Session; 

/**
 * Production MongoDB adapter wrapping mongodb/mongodb Client.
 * Implements the existing MongoConnectionInterface methods.
 */
class MongoClientConnection implements MongoConnectionInterface
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function find(string $database, string $collection, array $filter = [], array $options = []): iterable
    {
        $coll = $this->client->selectCollection($database, $collection);
        $cursor = $coll->find($this->normalizeFilter($filter), $options);
        $out = [];
        foreach ($cursor as $doc) {
            $out[] = $this->docToArray($doc);
        }
        return $out;
    }

    public function insertOne(string $database, string $collection, array $document): string
    {
        $coll = $this->client->selectCollection($database, $collection);
        $result = $coll->insertOne($this->normalizeDocument($document));
        $id = $result->getInsertedId();
        return (string)$id;
    }

    public function updateOne(string $database, string $collection, array $filter, array $update, array $options = []): int
    {
        $coll = $this->client->selectCollection($database, $collection);
        $res = $coll->updateOne($this->normalizeFilter($filter), $update, $options);
        return $res->getModifiedCount();
    }

    public function deleteOne(string $database, string $collection, array $filter, array $options = []): int
    {
        $coll = $this->client->selectCollection($database, $collection);
        $res = $coll->deleteOne($this->normalizeFilter($filter), $options);
        return $res->getDeletedCount();
    }

    public function aggregate(string $database, string $collection, array $pipeline, array $options = []): iterable
    {
        $coll = $this->client->selectCollection($database, $collection);
        $cursor = $coll->aggregate($pipeline, $options);
        $out = [];
        foreach ($cursor as $doc) {
            $out[] = $this->docToArray($doc);
        }
        return $out;
    }

    public function upsertOne(string $database, string $collection, array $filter, array $update): string
    {
        $coll = $this->client->selectCollection($database, $collection);
        // Normalize _id in filter (supports $in handled by normalizeFilter)
        $filter = $this->normalizeFilter($filter);
        $res = $coll->updateOne($filter, $update, ['upsert' => true]);
        $up = $res->getUpsertedId();
        if ($up !== null) {
            return (string)$up;
        }
        // Not an upsert (matched existing). Best-effort: fetch one doc and return its _id as string.
        $doc = $coll->findOne($filter);
        if ($doc) {
            $arr = $this->docToArray($doc);
            return isset($arr['_id']) ? (string)$arr['_id'] : '';
        }
        return '';
    }

    public function withSession(callable $callback): mixed
    {
        /** @var Session $session */
        $session = $this->client->startSession();
        try {
            return $callback($this, $session);
        } finally {
            try { $session->endSession(); } catch (\Throwable) {}
        }
    }

    public function withTransaction(callable $callback): mixed
    {
        /** @var Session $session */
        $session = $this->client->startSession();
        try {
            $result = $session->startTransaction();
            $ret = $callback($this, $session);
            $session->commitTransaction();
            return $ret;
        } catch (\Throwable $e) {
            try { $session->abortTransaction(); } catch (\Throwable) {}
            throw $e;
        } finally {
            try { $session->endSession(); } catch (\Throwable) {}
        }
    }

    /** @param array<string,mixed> $filter */
    private function normalizeFilter(array $filter): array
    {
        // Recursively walk the filter and convert any _id string(s) that look like 24-hex to ObjectId
        $walker = function (&$node, $key = null) use (&$walker) {
            if (is_array($node)) {
                foreach ($node as $k => &$v) {
                    $walker($v, $k);
                }
                return;
            }
            if ($key === '_id' && is_string($node) && preg_match('/^[a-f\d]{24}$/i', $node)) {
                try { $node = new ObjectId($node); } catch (\Throwable) {}
            }
        };

        $convertIdContainer = function (&$value) use (&$convertIdContainer) {
            // Handle structures like ['_id' => ['$in' => ['...','...']]]
            if (is_string($value) && preg_match('/^[a-f\d]{24}$/i', $value)) {
                try { $value = new ObjectId($value); } catch (\Throwable) {}
                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => &$v) {
                    $convertIdContainer($v);
                }
            }
        };

        // Top-level traversal
        foreach ($filter as $k => &$v) {
            if ($k === '_id') {
                $convertIdContainer($v);
            } elseif (is_array($v)) {
                // Recurse into nested boolean operators ($and/$or) etc.
                foreach ($v as $kk => &$vv) {
                    if ($kk === '_id') {
                        $convertIdContainer($vv);
                    } elseif (is_array($vv)) {
                        foreach ($vv as $kkk => &$vvv) {
                            if ($kkk === '_id') {
                                $convertIdContainer($vvv);
                            }
                        }
                    }
                }
            }
        }
        unset($v);

        return $filter;
    }

    /** @param array<string,mixed> $doc */
    private function normalizeDocument(array $doc): array
    {
        if (isset($doc['_id']) && is_string($doc['_id']) && preg_match('/^[a-f\d]{24}$/i', $doc['_id'])) {
            try { $doc['_id'] = new ObjectId($doc['_id']); } catch (\Throwable) {}
        }
        return $doc;
    }

    /**
     * Convert BSON document or array to a plain associative array, including ObjectId cast to string.
     */
    private function docToArray(mixed $doc): array
    {
        if ($doc instanceof \MongoDB\Model\BSONDocument) {
            $doc = $doc->getArrayCopy();
        } elseif ($doc instanceof \ArrayObject) {
            $doc = $doc->getArrayCopy();
        }
        if (!is_array($doc)) {
            return [];
        }
        $out = [];
        foreach ($doc as $k => $v) {
            if ($v instanceof ObjectId) {
                $out[$k] = (string)$v;
            } elseif ($v instanceof \MongoDB\BSON\UTCDateTime) {
                $out[$k] = $v->toDateTime()->format('c');
            } elseif ($v instanceof \MongoDB\Model\BSONDocument || $v instanceof \ArrayObject) {
                $out[$k] = $this->docToArray($v);
            } elseif (is_array($v)) {
                $out[$k] = array_map(function ($item) {
                    if ($item instanceof ObjectId) return (string)$item;
                    if ($item instanceof \MongoDB\Model\BSONDocument || $item instanceof \ArrayObject) {
                        return $this->docToArray($item);
                    }
                    return $item;
                }, $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}

<?php

namespace Pairity\NoSql\Mongo;

interface MongoConnectionInterface
{
    /** @return iterable<int, array<string,mixed>> */
    public function find(string $database, string $collection, array $filter = [], array $options = []): iterable;

    /** @param array<string,mixed> $document */
    public function insertOne(string $database, string $collection, array $document): string;

    /** @param array<string,mixed> $filter @param array<string,mixed> $update */
    public function updateOne(string $database, string $collection, array $filter, array $update, array $options = []): int;

    /** @param array<string,mixed> $filter */
    public function deleteOne(string $database, string $collection, array $filter, array $options = []): int;

    /** @param array<int, array<string,mixed>> $pipeline */
    public function aggregate(string $database, string $collection, array $pipeline, array $options = []): iterable;

    /** @param array<string,mixed> $filter @param array<string,mixed> $update */
    public function upsertOne(string $database, string $collection, array $filter, array $update): string;

    /** Execute a callback with a client session; callback receives the connection instance and session as args. */
    public function withSession(callable $callback): mixed;

    /** Execute a callback wrapped in a driver transaction when supported. */
    public function withTransaction(callable $callback): mixed;
}

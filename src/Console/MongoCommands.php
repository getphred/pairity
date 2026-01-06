<?php

namespace Pairity\Console;

use Pairity\NoSql\Mongo\MongoConnectionManager;
use Pairity\NoSql\Mongo\IndexManager;

abstract class AbstractMongoCommand extends AbstractCommand
{
    protected function getMongoConnection(array $args): \Pairity\NoSql\Mongo\MongoConnectionInterface
    {
        $config = $this->loadConfig($args);
        return MongoConnectionManager::make($config);
    }
}

class MongoIndexEnsureCommand extends AbstractMongoCommand
{
    public function execute(array $args): void
    {
        $db = $args[0] ?? null;
        $col = $args[1] ?? null;
        $keysJson = $args[2] ?? null;
        
        if (!$db || !$col || !$keysJson) {
            $this->stderr('Usage: pairity mongo:index:ensure DB COLLECTION KEYS_JSON [--unique]');
            exit(1);
        }

        $keys = json_decode($keysJson, true);
        if (!is_array($keys)) {
            $this->stderr('Invalid KEYS_JSON');
            exit(1);
        }

        $options = [];
        if (isset($args['unique']) && $args['unique']) {
            $options['unique'] = true;
        }

        $conn = $this->getMongoConnection($args);
        $mgr = new IndexManager($conn, $db, $col);
        $name = $mgr->ensureIndex($keys, $options);
        
        $this->stdout("Index created: {$name}");
    }
}

class MongoIndexDropCommand extends AbstractMongoCommand
{
    public function execute(array $args): void
    {
        $db = $args[0] ?? null;
        $col = $args[1] ?? null;
        $name = $args[2] ?? null;
        
        if (!$db || !$col || !$name) {
            $this->stderr('Usage: pairity mongo:index:drop DB COLLECTION NAME');
            exit(1);
        }

        $conn = $this->getMongoConnection($args);
        $mgr = new IndexManager($conn, $db, $col);
        $mgr->dropIndex($name);
        
        $this->stdout("Index dropped: {$name}");
    }
}

class MongoIndexListCommand extends AbstractMongoCommand
{
    public function execute(array $args): void
    {
        $db = $args[0] ?? null;
        $col = $args[1] ?? null;
        
        if (!$db || !$col) {
            $this->stderr('Usage: pairity mongo:index:list DB COLLECTION');
            exit(1);
        }

        $conn = $this->getMongoConnection($args);
        $mgr = new IndexManager($conn, $db, $col);
        $indexes = $mgr->listIndexes();
        
        $this->stdout(json_encode($indexes, JSON_PRETTY_PRINT));
    }
}

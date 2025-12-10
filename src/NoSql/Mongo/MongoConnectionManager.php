<?php

namespace Pairity\NoSql\Mongo;

use MongoDB\Client;

final class MongoConnectionManager
{
    /**
     * Build a MongoClientConnection from config.
     *
     * Supported keys:
     * - uri: full MongoDB URI (takes precedence)
     * - hosts: string|array host(s) (default 127.0.0.1)
     * - port: int (default 27017)
     * - username, password
     * - authSource: string
     * - replicaSet: string
     * - tls: bool
     * - uriOptions: array (MongoDB URI options)
     * - driverOptions: array (MongoDB driver options)
     *
     * @param array<string,mixed> $config
     */
    public static function make(array $config): MongoClientConnection
    {
        $uri = (string)($config['uri'] ?? '');
        $uriOptions = (array)($config['uriOptions'] ?? []);
        $driverOptions = (array)($config['driverOptions'] ?? []);

        if ($uri === '') {
            $hosts = $config['hosts'] ?? ($config['host'] ?? '127.0.0.1');
            $port = (int)($config['port'] ?? 27017);
            $hostsStr = '';
            if (is_array($hosts)) {
                $parts = [];
                foreach ($hosts as $h) { $parts[] = $h . ':' . $port; }
                $hostsStr = implode(',', $parts);
            } else {
                $hostsStr = (string)$hosts . ':' . $port;
            }
            $user = isset($config['username']) ? (string)$config['username'] : '';
            $pass = isset($config['password']) ? (string)$config['password'] : '';
            $auth = ($user !== '' && $pass !== '') ? ($user . ':' . $pass . '@') : '';

            $query = [];
            if (!empty($config['authSource'])) { $query['authSource'] = (string)$config['authSource']; }
            if (!empty($config['replicaSet'])) { $query['replicaSet'] = (string)$config['replicaSet']; }
            if (isset($config['tls'])) { $query['tls'] = $config['tls'] ? 'true' : 'false'; }
            $qs = $query ? ('?' . http_build_query($query)) : '';

            $uri = 'mongodb://' . $auth . $hostsStr . '/' . $qs;
        }

        $client = new Client($uri, $uriOptions, $driverOptions);
        return new MongoClientConnection($client);
    }
}

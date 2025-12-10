<?php

namespace Pairity\Migrations;

use Pairity\Contracts\ConnectionInterface;

interface MigrationInterface
{
    public function up(ConnectionInterface $connection): void;
    public function down(ConnectionInterface $connection): void;
}

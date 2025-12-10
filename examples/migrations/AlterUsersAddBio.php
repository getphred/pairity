<?php

declare(strict_types=1);

use Pairity\Migrations\MigrationInterface;
use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

return new class implements MigrationInterface {
    public function up(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->table('users', function (Blueprint $t) {
            // Add a new nullable column and an index (on status)
            $t->string('bio', 500)->nullable();
            $t->index(['status'], 'users_status_index');
        });
    }

    public function down(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->table('users', function (Blueprint $t) {
            $t->dropIndex('users_status_index');
            $t->dropColumn('bio');
        });
    }
};

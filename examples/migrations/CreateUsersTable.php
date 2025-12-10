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
        $schema->create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('email', 190);
            $t->unique(['email']);
            $t->string('name', 255)->nullable();
            $t->string('status', 50)->nullable();
            $t->timestamps();
            $t->datetime('deleted_at')->nullable();
        });
    }

    public function down(ConnectionInterface $connection): void
    {
        $schema = SchemaManager::forConnection($connection);
        $schema->dropIfExists('users');
    }
};

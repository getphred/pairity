<?php

declare(strict_types=1);

namespace Pairity\Database\Migrations;

use Pairity\Contracts\Database\DatabaseManagerInterface;

/**
 * Class DataMigration
 *
 * Base class for all procedural data migrations.
 */
abstract class DataMigration
{
    /**
     * DataMigration constructor.
     *
     * @param DatabaseManagerInterface $db
     */
    public function __construct(
        protected DatabaseManagerInterface $db
    ) {
    }

    /**
     * Execute the data migration.
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Roll back the data migration.
     *
     * @return void
     */
    abstract public function down(): void;
}

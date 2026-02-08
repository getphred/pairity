<?php

declare(strict_types=1);

namespace Pairity\Database\Seeding;

use Pairity\Contracts\Database\DatabaseManagerInterface;

/**
 * Class Seeder
 *
 * Base class for all database seeders.
 */
abstract class Seeder
{
    /**
     * @var DatabaseManagerInterface
     */
    protected DatabaseManagerInterface $db;

    /**
     * Seeder constructor.
     *
     * @param DatabaseManagerInterface $db
     */
    public function __construct(DatabaseManagerInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    abstract public function run(): void;

    /**
     * Seed the given seeder class.
     *
     * @param string $class
     * @return void
     */
    public function call(string $class): void
    {
        $seeder = new $class($this->db);
        $seeder->run();
    }
}

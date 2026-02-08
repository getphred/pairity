<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class SeedCommand
 *
 * CLI command to seed the database with records.
 */
class SeedCommand implements CommandInterface
{
    /**
     * SeedCommand constructor.
     *
     * @param DatabaseManagerInterface $db
     * @param TranslatorInterface $translator
     */
    public function __construct(
        protected DatabaseManagerInterface $db,
        protected TranslatorInterface $translator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'db:seed';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.db_seed.description', 'Seed the database with records.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $class = $args[0] ?? 'DatabaseSeeder';

        if (!str_contains($class, '\\')) {
            $class = 'App\\Database\\Seeds\\' . $class;
        }

        if (!class_exists($class)) {
            echo "Error: Seeder class [{$class}] not found.\n";
            return 1;
        }

        echo "Seeding: {$class}...\n";

        /** @var \Pairity\Database\Seeding\Seeder $seeder */
        $seeder = new $class($this->db);
        $seeder->run();

        echo "Database seeding completed successfully.\n";

        return 0;
    }
}

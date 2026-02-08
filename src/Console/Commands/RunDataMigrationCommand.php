<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class RunDataMigrationCommand
 *
 * CLI command to execute procedural PHP data migrations.
 */
class RunDataMigrationCommand implements CommandInterface
{
    /**
     * RunDataMigrationCommand constructor.
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
        return 'migration:data';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.migration_data.description', 'Execute procedural PHP data migrations.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $class = $args[0] ?? null;

        if (!$class) {
            echo "Error: Data migration class is required.\n";
            return 1;
        }

        if (!str_contains($class, '\\')) {
            $class = 'App\\Database\\Migrations\\' . $class;
        }

        if (!class_exists($class)) {
            echo "Error: Data migration class [{$class}] not found.\n";
            return 1;
        }

        echo "Executing Data Migration: {$class}...\n";

        /** @var \Pairity\Database\Migrations\DataMigration $migration */
        $migration = new $class($this->db);
        
        $rollback = in_array('--rollback', $options);

        if ($rollback) {
            $migration->down();
            echo "Data Migration rolled back successfully.\n";
        } else {
            $migration->up();
            echo "Data Migration executed successfully.\n";
        }

        return 0;
    }
}

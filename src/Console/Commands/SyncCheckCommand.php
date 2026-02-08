<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class SyncCheckCommand
 *
 * CLI command to verify synchronization of manual migration files and seed files.
 */
class SyncCheckCommand implements CommandInterface
{
    /**
     * SyncCheckCommand constructor.
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
        return 'db:check:sync';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.db_check_sync.description', 'Verify synchronization of manual migration files and seed files.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        echo "Checking synchronization...\n";

        // This is a stub implementation as we don't have a migrations table yet.
        // In a real implementation, this would compare files in filesystem with records in a meta-table.
        
        $migrationsDir = 'src/Database/Migrations';
        $seedsDir = 'src/Database/Seeds';

        $migrations = is_dir($migrationsDir) ? glob($migrationsDir . '/*.sql') : [];
        $seeds = is_dir($seedsDir) ? glob($seedsDir . '/*.php') : [];

        echo "Found " . count($migrations) . " manual migration(s).\n";
        echo "Found " . count($seeds) . " seed(s).\n";

        echo "Synchronization check completed. (Note: Full tracking requires migration table implementation in next phases).\n";

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class MakeMigrationCommand
 *
 * CLI command to create a new manual migration file.
 */
class MakeMigrationCommand implements CommandInterface
{
    /**
     * MakeMigrationCommand constructor.
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
        return 'make:migration';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.make_migration.description', 'Create a new manual migration file for custom SQL or data changes.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "Error: Migration name is required.\n";
            return 1;
        }

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $name . '.sql';
        $path = 'src/Database/Migrations/' . $filename;

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = "-- Pairity Migration: {$name}\n-- Created at: " . date('Y-m-d H:i:s') . "\n\n";

        file_put_contents($path, $content);

        echo "Migration created successfully at [{$path}].\n";

        return 0;
    }
}

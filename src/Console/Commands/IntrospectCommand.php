<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Database\Schema\Introspector;
use Symfony\Component\Yaml\Yaml;

/**
 * Class IntrospectCommand
 *
 * CLI command to reverse-engineer an existing database to generate YAML schema files.
 */
class IntrospectCommand implements CommandInterface
{
    /**
     * IntrospectCommand constructor.
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
        return 'make:yaml:fromdb';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.make_yaml_fromdb.description', 'Reverse-engineer an existing database to generate YAML schema files.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $connectionName = $options['connection'] ?? $this->db->getDefaultConnection();
        $connection = $this->db->connection($connectionName);
        $introspector = new Introspector($connection);

        $tables = $introspector->getTables();
        
        if (empty($tables)) {
            echo "No tables found in database [{$connectionName}].\n";
            return 0;
        }

        $outputDir = 'schema';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        echo "Introspecting database [{$connectionName}]...\n";

        foreach ($tables as $table) {
            echo "  - {$table}\n";
            $schema = $introspector->introspectTable($table);
            $yaml = Yaml::dump($schema, 4);
            file_put_contents($outputDir . '/' . $table . '.yaml', $yaml);
        }

        echo "Schema files generated successfully in [{$outputDir}].\n";

        return 0;
    }
}

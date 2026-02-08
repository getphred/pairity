<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Schema\YamlSchemaParser;
use Pairity\Schema\BlueprintSerializer;
use RuntimeException;

/**
 * Class GenerateSchemaSnapshotCommand
 *
 * CLI command to generate a schema snapshot from YAML definitions.
 *
 * @package Pairity\Console\Commands
 */
class GenerateSchemaSnapshotCommand implements CommandInterface
{
    /**
     * GenerateSchemaSnapshotCommand constructor.
     *
     * @param YamlSchemaParser $parser
     * @param BlueprintSerializer $serializer
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected YamlSchemaParser $parser,
        protected BlueprintSerializer $serializer,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'make:yaml:snapshot';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.schema_snapshot.description', 'Export the current YAML source of truth into a PHP baseline snapshot.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $schemaPath = $args[0] ?? 'schema';
        $snapshotPath = $args[1] ?? 'schema/snapshots';

        if (!is_dir($schemaPath)) {
            echo $this->t('command.schema_snapshot.no_directory', 'Schema directory not found: {path}', ['path' => $schemaPath]) . "\n";
            return 1;
        }

        if (!is_dir($snapshotPath)) {
            mkdir($snapshotPath, 0755, true);
        }

        $files = glob($schemaPath . '/*.yaml');
        if (empty($files)) {
            echo $this->t('command.schema_snapshot.no_files', 'No YAML schema files found in: {path}', ['path' => $schemaPath]) . "\n";
            return 0;
        }

        echo $this->t('command.schema_snapshot.starting', 'Generating schema snapshots...') . "\n";

        foreach ($files as $file) {
            $tableName = pathinfo($file, PATHINFO_FILENAME);
            try {
                $blueprint = $this->parser->parseFile($file);
                $phpCode = $this->serializer->toPhpCode($blueprint);
                
                $outputFile = $snapshotPath . DIRECTORY_SEPARATOR . $tableName . '.php';
                file_put_contents($outputFile, $phpCode);
                
                echo "  - {$tableName} -> {$outputFile}\n";
            } catch (\Throwable $e) {
                echo $this->t('command.schema_snapshot.error', 'Error processing {file}: {message}', ['file' => $file, 'message' => $e->getMessage()]) . "\n";
                return 1;
            }
        }

        echo "\n" . $this->t('command.schema_snapshot.finished', 'Snapshot generation completed successfully.') . "\n";
        return 0;
    }

    /**
     * Translate a message if a Translator is available.
     *
     * @param string $key
     * @param string $default
     * @param array $replace
     * @return string
     */
    protected function t(string $key, string $default, array $replace = []): string
    {
        if ($this->translator) {
            return $this->translator->trans($key, $replace);
        }

        foreach ($replace as $placeholder => $value) {
            $default = str_replace('{' . $placeholder . '}', (string) $value, $default);
        }

        return $default;
    }
}

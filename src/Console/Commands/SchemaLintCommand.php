<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Schema\YamlSchemaParser;
use Pairity\Exceptions\SchemaException;

/**
 * Class SchemaLintCommand
 *
 * CLI command to lint Pairity YAML table definitions.
 *
 * @package Pairity\Console\Commands
 */
class SchemaLintCommand implements CommandInterface
{
    /**
     * SchemaLintCommand constructor.
     *
     * @param YamlSchemaParser $parser
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected YamlSchemaParser $parser,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'schema:lint';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.schema_lint.description', 'Lint Pairity YAML table definitions in the schema directory.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $schemaPath = $args[0] ?? 'schema';

        if (!is_dir($schemaPath)) {
            echo $this->t('command.schema_lint.no_directory', 'Schema directory not found: {path}', ['path' => $schemaPath]) . "\n";
            return 1;
        }

        $files = glob($schemaPath . '/*.yaml');
        if (empty($files)) {
            echo $this->t('command.schema_lint.no_files', 'No YAML schema files found in: {path}', ['path' => $schemaPath]) . "\n";
            return 0;
        }

        $errors = 0;
        foreach ($files as $file) {
            try {
                echo $this->t('command.schema_lint.checking', 'Linting {file}...', ['file' => $file]) . " ";
                $this->parser->parseFile($file);
                echo $this->t('command.schema_lint.ok', 'OK') . "\n";
            } catch (SchemaException $e) {
                echo $this->t('command.schema_lint.error', 'ERROR: {message}', ['message' => $e->getMessage()]) . "\n";
                $errors++;
            }
        }

        if ($errors > 0) {
            echo "\n" . $this->t('command.schema_lint.finished_errors', 'Linting finished with {count} error(s).', ['count' => $errors]) . "\n";
            return 1;
        }

        echo "\n" . $this->t('command.schema_lint.finished_success', 'All schema files are valid.') . "\n";
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

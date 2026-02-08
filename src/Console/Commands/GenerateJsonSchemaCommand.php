<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Schema\JsonSchemaGenerator;

/**
 * Class GenerateJsonSchemaCommand
 *
 * CLI command to generate the JSON Schema for Pairity YAML table definitions.
 *
 * @package Pairity\Console\Commands
 */
class GenerateJsonSchemaCommand implements CommandInterface
{
    /**
     * GenerateJsonSchemaCommand constructor.
     *
     * @param JsonSchemaGenerator $generator
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected JsonSchemaGenerator $generator,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'schema:json';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.schema_json.description', 'Generate the JSON Schema for Pairity YAML table definitions.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $outputFile = $args[0] ?? 'pairity-schema.json';

        try {
            $json = $this->generator->generateJson();
            file_put_contents($outputFile, $json);

            echo $this->t('command.schema_json.success', 'JSON Schema generated successfully at: {path}', ['path' => $outputFile]) . "\n";
            return 0;
        } catch (\Throwable $e) {
            echo $this->t('command.schema_json.error', 'Error generating JSON Schema: {message}', ['message' => $e->getMessage()]) . "\n";
            return 1;
        }
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

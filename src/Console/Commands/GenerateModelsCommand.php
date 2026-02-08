<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Schema\YamlSchemaParser;
use Pairity\Schema\CodeGenerator;
use RuntimeException;

/**
 * Class GenerateModelsCommand
 *
 * CLI command to generate DTO and DAO classes from YAML schema definitions.
 *
 * @package Pairity\Console\Commands
 */
class GenerateModelsCommand implements CommandInterface
{
    /**
     * GenerateModelsCommand constructor.
     *
     * @param YamlSchemaParser $parser
     * @param CodeGenerator $generator
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected YamlSchemaParser $parser,
        protected CodeGenerator $generator,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'make:model';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.make_model.description', 'Generate DTO and DAO classes from YAML schema definitions.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $schemaPath = $args[0] ?? 'schema';
        $outputPath = $args[1] ?? 'src/Models'; // Default output path
        
        $namespace = 'App\\Models'; // Default namespace

        if (!is_dir($schemaPath)) {
            echo $this->t('command.make_model.no_directory', 'Schema directory not found: {path}', ['path' => $schemaPath]) . "\n";
            return 1;
        }

        $files = glob($schemaPath . '/*.yaml');
        if (empty($files)) {
            echo $this->t('command.make_model.no_files', 'No YAML schema files found in: {path}', ['path' => $schemaPath]) . "\n";
            return 0;
        }

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        echo $this->t('command.make_model.starting', 'Generating DTO and DAO classes...') . "\n";

        foreach ($files as $file) {
            try {
                $blueprint = $this->parser->parseFile($file);
                
                // Generate DTO
                $dtoCode = $this->generator->generateDto($blueprint, $namespace . '\\DTO');
                $dtoPath = $outputPath . DIRECTORY_SEPARATOR . 'DTO';
                if (!is_dir($dtoPath)) mkdir($dtoPath, 0755, true);
                $dtoFileName = $dtoPath . DIRECTORY_SEPARATOR . $this->studly($blueprint->getTableName()) . 'DTO.php';
                file_put_contents($dtoFileName, $dtoCode);

                // Generate DAO
                $daoCode = $this->generator->generateDao($blueprint, $namespace . '\\DAO', $namespace . '\\DTO');
                $daoPath = $outputPath . DIRECTORY_SEPARATOR . 'DAO';
                if (!is_dir($daoPath)) mkdir($daoPath, 0755, true);
                $daoFileName = $daoPath . DIRECTORY_SEPARATOR . $this->studly($blueprint->getTableName()) . 'DAO.php';
                file_put_contents($daoFileName, $daoCode);

                // Generate Hydrator
                $hydratorCode = $this->generator->generateHydrator($blueprint, $namespace . '\\Hydrators', $namespace . '\\DTO\\' . $this->studly($blueprint->getTableName()) . 'DTO');
                $hydratorPath = $outputPath . DIRECTORY_SEPARATOR . 'Hydrators';
                if (!is_dir($hydratorPath)) mkdir($hydratorPath, 0755, true);
                $hydratorFileName = $hydratorPath . DIRECTORY_SEPARATOR . $this->studly($blueprint->getTableName()) . 'Hydrator.php';
                file_put_contents($hydratorFileName, $hydratorCode);

                echo "  - {$blueprint->getTableName()} -> " . $this->studly($blueprint->getTableName()) . " DTO/DAO/Hydrator\n";
            } catch (\Throwable $e) {
                echo "  - Error processing {$file}: {$e->getMessage()}\n";
                return 1;
            }
        }

        echo "\n" . $this->t('command.make_model.finished', 'Code generation completed successfully.') . "\n";
        return 0;
    }

    /**
     * Convert string to StudlyCase.
     *
     * @param string $value
     * @return string
     */
    protected function studly(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
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

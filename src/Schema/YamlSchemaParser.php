<?php

declare(strict_types=1);

namespace Pairity\Schema;

use Pairity\Exceptions\SchemaException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Class YamlSchemaParser
 *
 * Parses YAML table definitions into Blueprint objects.
 *
 * @package Pairity\Schema
 */
class YamlSchemaParser
{
    /**
     * YamlSchemaParser constructor.
     *
     * @param TypeMapper $typeMapper
     */
    public function __construct(
        protected TypeMapper $typeMapper = new TypeMapper()
    ) {
    }

    /**
     * Parse a YAML file into a Blueprint.
     *
     * @param string $filePath
     * @return Blueprint
     * @throws SchemaException
     */
    public function parseFile(string $filePath): Blueprint
    {
        if (!file_exists($filePath)) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new SchemaException($translator->trans('error.schema_file_not_found', ['path' => $filePath]), 0, null, ['path' => $filePath]);
        }

        try {
            $data = Yaml::parseFile($filePath);
        } catch (ParseException $e) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new SchemaException($translator->trans('error.schema_parse_failed', ['path' => $filePath, 'message' => $e->getMessage()]), 0, $e, ['path' => $filePath]);
        }

        $tableName = pathinfo($filePath, PATHINFO_FILENAME);
        
        return $this->parse($tableName, $data);
    }

    /**
     * Parse a raw YAML string into a Blueprint.
     *
     * @param string $tableName
     * @param string $yamlString
     * @return Blueprint
     * @throws SchemaException
     */
    public function parseYaml(string $tableName, string $yamlString): Blueprint
    {
        try {
            $data = Yaml::parse($yamlString);
        } catch (ParseException $e) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new SchemaException($translator->trans('error.schema_parse_failed', ['path' => 'raw_yaml', 'message' => $e->getMessage()]), 0, $e);
        }

        if (!is_array($data)) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new SchemaException($translator->trans('error.schema_invalid_content'));
        }

        return $this->parse($tableName, $data);
    }

    /**
     * Parse raw YAML data into a Blueprint.
     *
     * @param string $tableName
     * @param array<string, mixed> $data
     * @return Blueprint
     * @throws SchemaException
     */
    public function parse(string $tableName, array $data): Blueprint
    {
        $builder = new Builder($tableName, $this->typeMapper);
        $blueprint = $builder->getBlueprint();

        $this->parseOptions($blueprint, $data);
        $this->parseColumns($builder, $data['columns'] ?? []);
        $this->parseIndexes($blueprint, $data['indexes'] ?? []);
        $this->parseRelations($blueprint, $data['relations'] ?? []);

        return $blueprint;
    }

    /**
     * Parse table options from YAML data.
     *
     * @param Blueprint $blueprint
     * @param array<string, mixed> $data
     * @return void
     */
    protected function parseOptions(Blueprint $blueprint, array $data): void
    {
        $options = [
            'prefix', 'tenancy', 'inheritance', 'morph', 
            'timestamps', 'softDeletes', 'auditable', 'view', 'locking'
        ];

        foreach ($options as $option) {
            if (isset($data[$option])) {
                $blueprint->setOption($option, $data[$option]);
            }
        }
    }

    /**
     * Parse columns from YAML data.
     *
     * @param Builder $builder
     * @param array<string, mixed> $columns
     * @return void
     * @throws SchemaException
     */
    protected function parseColumns(Builder $builder, array $columns): void
    {
        foreach ($columns as $name => $definition) {
            if (is_string($definition)) {
                $builder->addColumn($name, $definition);
                continue;
            }

            if (!is_array($definition) || !isset($definition['type'])) {
                $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
                throw new SchemaException($translator->trans('error.schema_invalid_column', ['column' => $name]), 0, null, ['column' => $name]);
            }

            $type = $definition['type'];
            unset($definition['type']);

            $column = $builder->addColumn($name, $type, $definition);

            // Handle fluent-like attributes if they are present in the array
            if (isset($definition['nullable'])) $column->nullable((bool)$definition['nullable']);
            if (isset($definition['unique'])) $column->unique((bool)$definition['unique']);
            if (isset($definition['primary'])) $column->primary((bool)$definition['primary']);
            if (isset($definition['index'])) $column->index((bool)$definition['index']);
            if (isset($definition['encrypted'])) $column->encrypted((bool)$definition['encrypted']);
            if (isset($definition['unsigned'])) $column->unsigned((bool)$definition['unsigned']);
            if (isset($definition['autoIncrement'])) $column->autoIncrement((bool)$definition['autoIncrement']);
            if (isset($definition['default'])) $column->default($definition['default']);
        }
    }

    /**
     * Parse indexes from YAML data.
     *
     * @param Blueprint $blueprint
     * @param array<string, mixed> $indexes
     * @return void
     */
    protected function parseIndexes(Blueprint $blueprint, array $indexes): void
    {
        foreach ($indexes as $name => $columns) {
            $blueprint->addIndex($name, (array) $columns);
        }
    }

    /**
     * Parse relations from YAML data.
     *
     * @param Blueprint $blueprint
     * @param array<string, mixed> $relations
     * @return void
     */
    protected function parseRelations(Blueprint $blueprint, array $relations): void
    {
        foreach ($relations as $name => $definition) {
            $blueprint->addRelation($name, (array) $definition);
        }
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Schema;

use Pairity\Exceptions\SchemaException;

/**
 * Class CodeGenerator
 *
 * Generates DTO and DAO code from Blueprint objects.
 *
 * @package Pairity\Schema
 */
class CodeGenerator
{
    /**
     * CodeGenerator constructor.
     *
     * @param string $stubsPath
     * @param string $namespacePrefix
     */
    public function __construct(
        protected string $stubsPath,
        protected string $namespacePrefix = 'App'
    ) {
    }

    /**
     * Generate DTO code for a blueprint.
     *
     * @param Blueprint $blueprint
     * @param string $namespace
     * @return string
     * @throws SchemaException
     */
    public function generateDto(Blueprint $blueprint, string $namespace): string
    {
        $stub = $this->getStub('DTO');
        $className = $this->getClassName($blueprint->getTableName()) . 'DTO';

        $properties = [];
        $constructor = [];
        $methods = [];
        $toArray = [];

        foreach ($blueprint->getColumns() as $column) {
            $name = $column->getName();
            $properties[] = "    protected \${$name};";
            $constructor[] = "        \$this->{$name} = \$attributes['{$name}'] ?? null;";
            
            // Basic getter
            $studlyName = $this->studly($name);
            $methodStub = "    /**\n     * @return mixed\n     */\n    public function get{$studlyName}()\n    {\n        \$this->ensureLoaded();\n        return \$this->{$name};\n    }";
            $methods[] = $methodStub;
            
            $toArray[] = "            '{$name}' => \$this->{$name},";
        }

        return str_replace(
            ['{{namespace}}', '{{class}}', '{{properties}}', '{{constructor}}', '{{methods}}', '{{toArray}}'],
            [
                $namespace, 
                $className, 
                implode("\n", $properties), 
                implode("\n", $constructor), 
                implode("\n\n", $methods), 
                implode("\n", $toArray)
            ],
            $stub
        );
    }

    /**
     * Generate DAO code for a blueprint.
     *
     * @param Blueprint $blueprint
     * @param string $namespace
     * @param string $dtoNamespace
     * @return string
     * @throws SchemaException
     */
    public function generateDao(Blueprint $blueprint, string $namespace, string $dtoNamespace): string
    {
        $stub = $this->getStub('DAO');
        $baseName = $this->getClassName($blueprint->getTableName());
        $className = $baseName . 'DAO';
        $dtoClass = $baseName . 'DTO';
        $dtoFqcn = $dtoNamespace . '\\' . $dtoClass;

        $primaryKey = 'id'; // Default
        foreach ($blueprint->getColumns() as $column) {
            if ($column->getAttribute('primary')) {
                $primaryKey = $column->getName();
                break;
            }
        }

        $scopes = $this->generateScopes($blueprint);
        $relations = $this->generateRelations($blueprint, $dtoNamespace, $namespace);

        $auditable = $blueprint->getOption('auditable', false);
        $auditableConfig = "";
        if ($auditable) {
            $auditableConfig = "\n    /**\n     * @var bool\n     */\n    protected bool \$auditable = true;\n";
        }

        $locking = $blueprint->getOption('locking', false);
        $lockingConfig = "";
        if ($locking) {
            $lockingColumn = is_string($locking) ? $locking : 'version';
            $lockingConfig = "\n    /**\n     * @var string|null\n     */\n    protected ?string \$lockingColumn = '{$lockingColumn}';\n";
        }

        return str_replace(
            [
                '{{namespace}}', 
                '{{class}}', 
                '{{dto_fqcn}}', 
                '{{dto_class}}', 
                '{{connection}}', 
                '{{table}}', 
                '{{primary_key}}',
                '{{scopes}}',
                '{{relations}}',
                '{{locking}}',
                '{{auditable}}'
            ],
            [
                $namespace, 
                $className, 
                $dtoFqcn, 
                $dtoClass, 
                'default', 
                $blueprint->getTableName(), 
                $primaryKey,
                $scopes,
                $relations,
                $lockingConfig,
                $auditableConfig
            ],
            $stub
        );
    }

    /**
     * Generate relationship methods for a DAO.
     *
     * @param Blueprint $blueprint
     * @param string $dtoNamespace
     * @param string $daoNamespace
     * @return string
     */
    protected function generateRelations(Blueprint $blueprint, string $dtoNamespace, string $daoNamespace): string
    {
        $methods = [];
        foreach ($blueprint->getRelations() as $name => $definition) {
            $type = $definition['type'];
            $target = $definition['target'];
            $targetBaseName = $this->getClassName($target);
            $targetDtoFqcn = $dtoNamespace . '\\' . $targetBaseName . 'DTO';
            $targetDaoFqcn = $daoNamespace . '\\' . $targetBaseName . 'DAO';
            
            $foreignKey = $definition['foreign_key'] ?? ($type === 'belongsTo' ? $target . '_id' : $blueprint->getTableName() . '_id');
            $localKey = $definition['local_key'] ?? 'id';
            $morphType = $definition['morph_type'] ?? $name . '_type';

            $relationClass = 'Pairity\\Database\\Query\\Relations\\' . ucfirst($type);

            $methods[] = "    /**";
            $methods[] = "     * Get the {$name} relationship.";
            $methods[] = "     *";
            $methods[] = "     * @param \\Pairity\\DTO\\BaseDTO \$parent";
            $methods[] = "     * @return \\{$relationClass}";
            $methods[] = "     */";
            $methods[] = "    public function {$name}(\\Pairity\\DTO\\BaseDTO \$parent): \\{$relationClass}";
            $methods[] = "    {";
            $methods[] = "        \$relatedDao = \$this->db->getContainer()->get('{$targetDaoFqcn}');";
            if ($type === 'morphTo') {
                $methods[] = "        return new \\{$relationClass}(\$relatedDao->query(), \$parent, \$relatedDao, '{$foreignKey}', '{$localKey}', '{$morphType}');";
            } else {
                $methods[] = "        return new \\{$relationClass}(\$relatedDao->query(), \$parent, \$relatedDao, '{$foreignKey}', '{$localKey}');";
            }
            $methods[] = "    }";
            $methods[] = "";
        }

        return implode("\n", $methods);
    }

    /**
     * Generate query scopes for a blueprint.
     *
     * @param Blueprint $blueprint
     * @return string
     */
    protected function generateScopes(Blueprint $blueprint): string
    {
        // For now, we'll just add a placeholder or common scopes like 'whereId'
        // In the future, this can be expanded based on YAML 'scopes' key.
        return "";
    }

    /**
     * Generate Hydrator code for a blueprint.
     *
     * @param Blueprint $blueprint
     * @param string $namespace
     * @param string $dtoFqcn
     * @return string
     * @throws SchemaException
     */
    public function generateHydrator(Blueprint $blueprint, string $namespace, string $dtoFqcn): string
    {
        $stub = $this->getStub('Hydrator');
        $className = $this->getClassName($blueprint->getTableName()) . 'Hydrator';

        $logic = [];
        foreach ($blueprint->getColumns() as $column) {
            $name = $column->getName();
            $logic[] = "        if (isset(\$data['{$name}'])) {";
            $logic[] = "            if (!isset(self::\$reflectionProperties['{$name}'])) {";
            $logic[] = "                self::\$reflectionProperties['{$name}'] = new ReflectionProperty('{$dtoFqcn}', '{$name}');";
            $logic[] = "            }";
            $logic[] = "            self::\$reflectionProperties['{$name}']->setValue(\$instance, \$data['{$name}']);";
            $logic[] = "        }";
        }

        return str_replace(
            ['{{namespace}}', '{{class}}', '{{hydration_logic}}'],
            [$namespace, $className, implode("\n", $logic)],
            $stub
        );
    }

    /**
     * Get a stub by name.
     *
     * @param string $name
     * @return string
     * @throws SchemaException
     */
    protected function getStub(string $name): string
    {
        $path = $this->stubsPath . DIRECTORY_SEPARATOR . $name . '.stub';
        if (!file_exists($path)) {
            throw new SchemaException("Stub file [{$path}] not found.");
        }
        return file_get_contents($path);
    }

    /**
     * Convert table name to class name (StudlyCase).
     *
     * @param string $name
     * @return string
     */
    protected function getClassName(string $name): string
    {
        return $this->studly($name);
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
}

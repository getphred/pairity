<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class BlueprintSerializer
 *
 * Serializes Blueprint objects into various formats.
 *
 * @package Pairity\Schema
 */
class BlueprintSerializer
{
    /**
     * Serialize a Blueprint into a PHP array structure.
     *
     * @param Blueprint $blueprint
     * @return array<string, mixed>
     */
    public function toArray(Blueprint $blueprint): array
    {
        $columns = [];
        foreach ($blueprint->getColumns() as $column) {
            $columns[$column->getName()] = [
                'type' => $column->getType(),
                'attributes' => $column->getAttributes(),
            ];
        }

        return [
            'tableName' => $blueprint->getTableName(),
            'options' => $blueprint->getOptions(),
            'columns' => $columns,
            'indexes' => $blueprint->getIndexes(),
            'relations' => $blueprint->getRelations(),
        ];
    }

    /**
     * Serialize a Blueprint into a PHP code string (for snapshots).
     *
     * @param Blueprint $blueprint
     * @return string
     */
    public function toPhpCode(Blueprint $blueprint): string
    {
        $data = $this->toArray($blueprint);
        $exported = var_export($data, true);
        
        return "<?php\n\nreturn {$exported};\n";
    }
}

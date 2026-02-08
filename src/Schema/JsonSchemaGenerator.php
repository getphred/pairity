<?php

declare(strict_types=1);

namespace Pairity\Schema;

/**
 * Class JsonSchemaGenerator
 *
 * Generates a JSON Schema for Pairity YAML table definitions.
 *
 * @package Pairity\Schema
 */
class JsonSchemaGenerator
{
    /**
     * Generate the JSON schema as an array.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'Pairity Table Definition',
            'type' => 'object',
            'properties' => [
                'prefix' => ['type' => 'string'],
                'tenancy' => ['type' => 'boolean'],
                'auditable' => ['type' => 'boolean'],
                'timestamps' => ['type' => 'boolean'],
                'softDeletes' => ['type' => 'boolean'],
                'inheritance' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['sti', 'cti']],
                        'discriminator' => ['type' => 'string'],
                        'parent' => ['type' => 'string'],
                    ],
                ],
                'morph' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                    ],
                ],
                'columns' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'anyOf' => [
                            ['type' => 'string'],
                            [
                                'type' => 'object',
                                'required' => ['type'],
                                'properties' => [
                                    'type' => ['type' => 'string'],
                                    'length' => ['type' => 'integer'],
                                    'precision' => ['type' => 'integer'],
                                    'scale' => ['type' => 'integer'],
                                    'nullable' => ['type' => 'boolean'],
                                    'unique' => ['type' => 'boolean'],
                                    'primary' => ['type' => 'boolean'],
                                    'index' => ['type' => 'boolean'],
                                    'encrypted' => ['type' => 'boolean'],
                                    'unsigned' => ['type' => 'boolean'],
                                    'autoIncrement' => ['type' => 'boolean'],
                                    'default' => ['type' => ['string', 'number', 'boolean', 'null']],
                                    'comment' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'indexes' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'relations' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'object',
                        'required' => ['type', 'model'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany']],
                            'model' => ['type' => 'string'],
                            'foreignKey' => ['type' => 'string'],
                            'localKey' => ['type' => 'string'],
                            'pivotTable' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'required' => ['columns'],
        ];
    }

    /**
     * Generate the JSON schema and return it as a string.
     *
     * @return string
     */
    public function generateJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Schema;

use Pairity\Schema\YamlSchemaParser;
use PHPUnit\Framework\TestCase;

class YamlSchemaParserTest extends TestCase
{
    public function test_it_parses_yaml_data_correctly()
    {
        $yaml = [
            'tenancy' => true,
            'timestamps' => true,
            'columns' => [
                'id' => [
                    'type' => 'bigInteger',
                    'primary' => true,
                    'autoIncrement' => true,
                ],
                'email' => [
                    'type' => 'string',
                    'length' => 150,
                    'unique' => true,
                    'encrypted' => true,
                ],
                'status' => 'string',
            ],
            'indexes' => [
                'idx_status' => ['status'],
            ],
            'relations' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'Post',
                ],
            ],
        ];

        $parser = new YamlSchemaParser();
        $blueprint = $parser->parse('users', $yaml);

        $this->assertEquals('users', $blueprint->getTableName());
        $this->assertTrue($blueprint->getOption('tenancy'));
        
        $columns = $blueprint->getColumns();
        $this->assertCount(3, $columns);
        
        $this->assertEquals('email', $columns[1]->getName());
        $this->assertEquals(150, $columns[1]->getAttribute('length'));
        $this->assertTrue($columns[1]->getAttribute('unique'));
        $this->assertTrue($columns[1]->getAttribute('encrypted'));

        $this->assertArrayHasKey('idx_status', $blueprint->getIndexes());
        $this->assertArrayHasKey('posts', $blueprint->getRelations());
    }

    public function test_it_parses_yaml_string_correctly()
    {
        $yaml = <<<YAML
tenancy: true
columns:
  id:
    type: bigInteger
    primary: true
  email:
    type: string
    unique: true
YAML;

        $parser = new YamlSchemaParser();
        $blueprint = $parser->parseYaml('users', $yaml);

        $this->assertEquals('users', $blueprint->getTableName());
        $this->assertTrue($blueprint->getOption('tenancy'));
        $this->assertCount(2, $blueprint->getColumns());
        $this->assertEquals('email', $blueprint->getColumns()[1]->getName());
        $this->assertTrue($blueprint->getColumns()[1]->getAttribute('unique'));
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Schema;

use Pairity\Schema\Builder;
use Pairity\Schema\TypeMapper;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function test_it_creates_blueprint_with_correct_name()
    {
        $builder = new Builder('users');
        $blueprint = $builder->getBlueprint();

        $this->assertEquals('users', $blueprint->getTableName());
    }

    public function test_it_can_add_columns_fluently()
    {
        $builder = new Builder('users');
        $builder->id();
        $builder->string('email', 100)->unique();
        $builder->boolean('is_active')->default(true);
        $builder->timestamps();

        $blueprint = $builder->getBlueprint();
        $columns = $blueprint->getColumns();

        $this->assertCount(5, $columns); // id, email, is_active, created_at, updated_at
        
        $this->assertEquals('id', $columns[0]->getName());
        $this->assertTrue($columns[0]->getAttribute('primary'));
        
        $this->assertEquals('email', $columns[1]->getName());
        $this->assertEquals(100, $columns[1]->getAttribute('length'));
        $this->assertTrue($columns[1]->getAttribute('unique'));
        
        $this->assertEquals('is_active', $columns[2]->getName());
        $this->assertTrue($columns[2]->getAttribute('default'));
    }

    public function test_it_supports_magic_methods_for_types()
    {
        $builder = new Builder('products');
        $builder->decimal('price', ['precision' => 10, 'scale' => 2]);
        $builder->uuid('uuid');

        $columns = $builder->getBlueprint()->getColumns();

        $this->assertEquals('price', $columns[0]->getName());
        $this->assertEquals('decimal', $columns[0]->getType());
        $this->assertEquals(10, $columns[0]->getAttribute('precision'));

        $this->assertEquals('uuid', $columns[1]->getName());
        $this->assertEquals('uuid', $columns[1]->getType());
    }
}

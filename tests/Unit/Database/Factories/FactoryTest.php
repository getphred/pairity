<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Database\Factories;

use PHPUnit\Framework\TestCase;
use Pairity\Database\Factories\Factory;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Container\ContainerInterface;
use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;

class FactoryTest extends TestCase
{
    public function test_it_creates_model_with_default_definition()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $dao = $this->createMock(BaseDAO::class);
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->willReturn($dao);
        
        $factory = new class($db) extends Factory {
            public function definition(): array {
                return ['name' => 'Default Name', 'email' => 'default@example.com'];
            }
            public function model(): string {
                return \Pairity\Tests\Unit\Database\Factories\TestDTO::class;
            }
            public function dao(): string {
                return 'TestDAO';
            }
        };

        $dao->expects($this->once())->method('save');

        $model = $factory->create(['name' => 'Overridden Name']);

        $this->assertInstanceOf(TestDTO::class, $model);
        $this->assertEquals('Overridden Name', $model->name);
        $this->assertEquals('default@example.com', $model->email);
    }

    public function test_it_applies_states()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        
        $factory = new class($db) extends Factory {
            public function definition(): array {
                return ['name' => 'Default Name', 'role' => 'user'];
            }
            public function model(): string {
                return \Pairity\Tests\Unit\Database\Factories\TestDTO::class;
            }
            public function dao(): string {
                return 'TestDAO';
            }
            public function admin() {
                return $this->state(['role' => 'admin']);
            }
        };

        $model = $factory->admin()->make();

        $this->assertEquals('admin', $model->role);
    }

    public function test_it_creates_multiple_models()
    {
        $db = $this->createMock(DatabaseManagerInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $dao = $this->createMock(BaseDAO::class);
        
        $db->method('getContainer')->willReturn($container);
        $container->method('get')->willReturn($dao);
        
        $factory = new class($db) extends Factory {
            public function definition(): array {
                return ['name' => 'Test'];
            }
            public function model(): string {
                return \Pairity\Tests\Unit\Database\Factories\TestDTO::class;
            }
            public function dao(): string {
                return 'TestDAO';
            }
        };

        $dao->expects($this->exactly(3))->method('save');

        $models = $factory->count(3)->create();

        $this->assertCount(3, $models);
        $this->assertInstanceOf(TestDTO::class, $models[0]);
    }
}

class TestDTO extends BaseDTO
{
    public $name;
    public $email;
    public $role;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->name = $attributes['name'] ?? null;
        $this->email = $attributes['email'] ?? null;
        $this->role = $attributes['role'] ?? null;
    }

    public function __get($name) {
        return $this->$name;
    }
}

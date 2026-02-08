<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Schema;

use App\Models\DTO\UsersDTO;
use App\Models\Hydrators\UsersHydrator;
use PHPUnit\Framework\TestCase;

class HydratorTest extends TestCase
{
    public function test_it_hydrates_dto_correctly()
    {
        $data = [
            'id' => 123,
            'email' => 'test@example.com',
        ];

        $dto = new UsersDTO();
        $hydrator = new UsersHydrator();
        $hydrator->hydrate($data, $dto);

        $this->assertEquals(123, $dto->getId());
        $this->assertEquals('test@example.com', $dto->getEmail());
    }

    public function test_it_only_hydrates_provided_data()
    {
        $data = [
            'email' => 'only@example.com',
        ];

        $dto = new UsersDTO(['id' => 1]);
        $hydrator = new UsersHydrator();
        $hydrator->hydrate($data, $dto);

        $this->assertEquals(1, $dto->getId());
        $this->assertEquals('only@example.com', $dto->getEmail());
    }
}

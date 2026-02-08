<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\DTO;

use Pairity\DTO\IdentityMap;
use PHPUnit\Framework\TestCase;
use stdClass;

class IdentityMapTest extends TestCase
{
    public function test_it_can_store_and_retrieve_instances()
    {
        $map = new IdentityMap();
        $instance = new stdClass();
        
        $map->add('User', 1, $instance);
        
        $this->assertSame($instance, $map->get('User', 1));
        $this->assertNull($map->get('User', 2));
        $this->assertNull($map->get('Post', 1));
    }

    public function test_it_can_be_cleared()
    {
        $map = new IdentityMap();
        $instance = new stdClass();
        
        $map->add('User', 1, $instance);
        $map->clear();
        
        $this->assertNull($map->get('User', 1));
    }
}

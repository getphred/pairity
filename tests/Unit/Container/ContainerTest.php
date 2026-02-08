<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Container;

use Pairity\Container\Container;
use Pairity\Tests\TestCase;

/**
 * Class ContainerTest
 *
 * Unit tests for the Pairity Service Container.
 *
 * @package Pairity\Tests\Unit\Container
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    /**
     * Test that the container can bind and resolve a simple closure.
     */
    public function test_it_can_bind_and_resolve_closure(): void
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $this->container->make('foo'));
    }

    /**
     * Test that the container can bind and resolve a singleton.
     */
    public function test_it_can_bind_and_resolve_singleton(): void
    {
        $this->container->singleton('std', function () {
            return new \stdClass();
        });

        $instance1 = $this->container->make('std');
        $instance2 = $this->container->make('std');

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test that the container can resolve a class name directly.
     */
    public function test_it_can_resolve_class_directly(): void
    {
        $instance = $this->container->make(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $instance);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Events;

use Pairity\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
    public function test_it_can_register_and_dispatch_events()
    {
        $dispatcher = new Dispatcher();
        $fired = false;
        
        $dispatcher->listen('test.event', function ($payload) use (&$fired) {
            $fired = true;
            $this->assertEquals('hello', $payload);
        });

        $dispatcher->dispatch('test.event', 'hello');
        $this->assertTrue($fired);
    }

    public function test_it_handles_wildcards()
    {
        $dispatcher = new Dispatcher();
        $count = 0;

        $dispatcher->listen('user.*', function () use (&$count) {
            $count++;
        });

        $dispatcher->dispatch('user.created');
        $dispatcher->dispatch('user.updated');
        $dispatcher->dispatch('post.created');

        $this->assertEquals(2, $count);
    }

    public function test_it_can_halt_execution()
    {
        $dispatcher = new Dispatcher();
        
        $dispatcher->listen('test', function () {
            return 'first';
        });

        $dispatcher->listen('test', function () {
            return 'second';
        });

        $result = $dispatcher->dispatch('test', null, true);
        $this->assertEquals('first', $result);
    }

    public function test_returning_false_stops_propagation()
    {
        $dispatcher = new Dispatcher();
        $secondFired = false;

        $dispatcher->listen('test', function () {
            return false;
        });

        $dispatcher->listen('test', function () use (&$secondFired) {
            $secondFired = true;
        });

        $dispatcher->dispatch('test');
        $this->assertFalse($secondFired);
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Contracts\Events;

/**
 * Interface DispatcherInterface
 *
 * Defines the contract for the Pairity Event Dispatcher.
 */
interface DispatcherInterface
{
    /**
     * Register an event listener.
     *
     * @param string $event
     * @param callable $listener
     * @return void
     */
    public function listen(string $event, callable $listener): void;

    /**
     * Dispatch an event and call the listeners.
     *
     * @param string $event
     * @param mixed $payload
     * @param bool $halt
     * @return mixed
     */
    public function dispatch(string $event, mixed $payload = null, bool $halt = false): mixed;

    /**
     * Determine if a given event has listeners.
     *
     * @param string $event
     * @return bool
     */
    public function hasListeners(string $event): bool;
}

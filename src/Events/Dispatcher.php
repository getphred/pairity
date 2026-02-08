<?php

declare(strict_types=1);

namespace Pairity\Events;

use Pairity\Contracts\Events\DispatcherInterface;

/**
 * Class Dispatcher
 *
 * Lightweight event dispatcher implementation.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * @var array<string, array<callable>>
     */
    protected array $listeners = [];

    /**
     * @var array<string, array<callable>>
     */
    protected array $wildcards = [];

    /**
     * @inheritDoc
     */
    public function listen(string $event, callable $listener): void
    {
        if (str_contains($event, '*')) {
            $this->wildcards[$this->compileWildcard($event)][] = $listener;
        } else {
            $this->listeners[$event][] = $listener;
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatch(string $event, mixed $payload = null, bool $halt = false): mixed
    {
        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($payload);

            if ($halt && !is_null($response)) {
                return $response;
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    /**
     * @inheritDoc
     */
    public function hasListeners(string $event): bool
    {
        return count($this->getListeners($event)) > 0;
    }

    /**
     * Get all listeners for a given event, including wildcards.
     *
     * @param string $event
     * @return array<callable>
     */
    protected function getListeners(string $event): array
    {
        $listeners = $this->listeners[$event] ?? [];

        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if (preg_match($pattern, $event)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    /**
     * Compile a wildcard event name into a regex pattern.
     *
     * @param string $event
     * @return string
     */
    protected function compileWildcard(string $event): string
    {
        return '/^' . str_replace('\*', '.*', preg_quote($event, '/')) . '$/';
    }
}

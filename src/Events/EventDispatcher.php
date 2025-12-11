<?php

namespace Pairity\Events;

final class EventDispatcher
{
    /** @var array<string, array<int, array{priority:int, listener:callable}>> */
    private array $listeners = [];

    /**
     * Register a listener for an event.
     * Listener signature: function(array &$payload): void
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = ['priority' => $priority, 'listener' => $listener];
        // Sort by priority desc so higher runs first
        usort($this->listeners[$event], function ($a, $b) { return $b['priority'] <=> $a['priority']; });
    }

    /** Register all listeners from a subscriber. */
    public function subscribe(SubscriberInterface $subscriber): void
    {
        foreach ((array)$subscriber->getSubscribedEvents() as $event => $handler) {
            $callable = null; $priority = 0;
            if (is_array($handler) && isset($handler[0])) {
                $callable = $handler[0];
                $priority = (int)($handler[1] ?? 0);
            } else {
                $callable = $handler;
            }
            if (is_callable($callable)) {
                $this->listen($event, $callable, $priority);
            }
        }
    }

    /**
     * Dispatch an event with a mutable payload (passed by reference to listeners).
     *
     * @param string $event
     * @param array<string,mixed> $payload
     */
    public function dispatch(string $event, array &$payload = []): void
    {
        $list = $this->listeners[$event] ?? [];
        if (!$list) return;
        foreach ($list as $entry) {
            try {
                ($entry['listener'])($payload);
            } catch (\Throwable) {
                // swallow listener exceptions to avoid breaking core flow
            }
        }
    }

    /** Remove all listeners for an event or all events. */
    public function clear(?string $event = null): void
    {
        if ($event === null) { $this->listeners = []; return; }
        unset($this->listeners[$event]);
    }
}

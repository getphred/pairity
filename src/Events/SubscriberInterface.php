<?php

namespace Pairity\Events;

interface SubscriberInterface
{
    /**
     * Return an array of event => callable|array{0:callable,1:int priority}
     * Example: return [
     *   'dao.beforeInsert' => [[$this, 'onBeforeInsert'], 10],
     *   'uow.afterCommit'  => [$this, 'onAfterCommit'],
     * ];
     */
    public function getSubscribedEvents(): array;
}

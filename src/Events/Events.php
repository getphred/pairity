<?php

namespace Pairity\Events;

final class Events
{
    private static ?EventDispatcher $dispatcher = null;

    public static function dispatcher(): EventDispatcher
    {
        if (self::$dispatcher === null) {
            self::$dispatcher = new EventDispatcher();
        }
        return self::$dispatcher;
    }

    public static function setDispatcher(?EventDispatcher $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }
}

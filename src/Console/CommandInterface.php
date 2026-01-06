<?php

namespace Pairity\Console;

interface CommandInterface
{
    /**
     * Execute the command.
     *
     * @param array<string, mixed> $args
     */
    public function execute(array $args): void;
}

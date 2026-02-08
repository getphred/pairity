<?php

declare(strict_types=1);

namespace Pairity\DTO;

/**
 * Interface ProxyInterface
 *
 * Defines the contract for lazy-loading ghost objects.
 */
interface ProxyInterface
{
    /**
     * Load the full state of the proxy.
     *
     * @return void
     */
    public function __load(): void;

    /**
     * Check if the proxy has been initialized.
     *
     * @return bool
     */
    public function __isInitialized(): bool;
}

<?php

declare(strict_types=1);

namespace Pairity\Contracts\Container;

/**
 * Interface ContainerInterface
 *
 * Defines the contract for the Pairity Service Container.
 * This interface is loosely based on PSR-11 (ContainerInterface) to maintain
 * standard compatibility while allowing for ORM-specific optimizations.
 *
 * @package Pairity\Contracts\Container
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Register a binding with the container.
     *
     * @param string $abstract The abstract type (interface or class name).
     * @param mixed $concrete The concrete implementation (closure, class name, or instance).
     * @param bool $shared Whether the binding should be treated as a singleton.
     *
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Register a shared binding (singleton) with the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     *
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     *
     * @return mixed
     */
    public function make(string $abstract): mixed;
}

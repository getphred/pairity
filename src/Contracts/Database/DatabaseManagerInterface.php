<?php

declare(strict_types=1);

namespace Pairity\Contracts\Database;

/**
 * Interface DatabaseManagerInterface
 *
 * Defines the contract for managing multiple database connections.
 *
 * @package Pairity\Contracts\Database
 */
interface DatabaseManagerInterface
{
    /**
     * Get a database connection instance.
     *
     * @param string|null $name The connection name (defaults to default connection).
     * @return ConnectionInterface
     */
    public function connection(?string $name = null): ConnectionInterface;

    /**
     * Reconnect to the given database.
     *
     * @param string|null $name
     * @return ConnectionInterface
     */
    public function reconnect(?string $name = null): ConnectionInterface;

    /**
     * Disconnect from the given database.
     *
     * @param string|null $name
     * @return void
     */
    public function disconnect(?string $name = null): void;

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Get the query grammar for a driver.
     *
     * @param string $driver
     * @return \Pairity\Database\Query\Grammar
     */
    public function getQueryGrammar(string $driver): \Pairity\Database\Query\Grammar;

    /**
     * Get the event dispatcher instance.
     *
     * @return \Pairity\Contracts\Events\DispatcherInterface
     */
    public function getDispatcher(): \Pairity\Contracts\Events\DispatcherInterface;

    /**
     * Set the event dispatcher instance.
     *
     * @param \Pairity\Contracts\Events\DispatcherInterface $dispatcher
     * @return void
     */
    public function setDispatcher(\Pairity\Contracts\Events\DispatcherInterface $dispatcher): void;

    /**
     * Get the Unit of Work instance.
     *
     * @return \Pairity\Database\UnitOfWork
     */
    public function unitOfWork(): \Pairity\Database\UnitOfWork;

    /**
     * Get the container instance.
     *
     * @return \Pairity\Contracts\Container\ContainerInterface
     */
    public function getContainer(): \Pairity\Contracts\Container\ContainerInterface;
}

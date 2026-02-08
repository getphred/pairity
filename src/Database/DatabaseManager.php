<?php

declare(strict_types=1);

namespace Pairity\Database;

use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Database\DriverInterface;
use Pairity\Database\Drivers\MySQLDriver;
use Pairity\Database\Drivers\OracleDriver;
use Pairity\Database\Drivers\PostgresDriver;
use Pairity\Database\Drivers\SQLiteDriver;
use Pairity\Database\Drivers\SqlServerDriver;
use Pairity\Exceptions\PairityException;
use RuntimeException;

/**
 * Class DatabaseManager
 *
 * Manages database connections and their lifecycles.
 *
 * @package Pairity\Database
 */
class DatabaseManager implements DatabaseManagerInterface
{
    /**
     * @var array<string, ConnectionInterface>
     */
    protected array $connections = [];

    /**
     * @var array<string, DriverInterface>
     */
    protected array $drivers = [];

    /**
     * @var \Pairity\Contracts\Container\ContainerInterface|null
     */
    protected ?\Pairity\Contracts\Container\ContainerInterface $container = null;

    /**
     * @var UnitOfWork|null
     */
    protected ?UnitOfWork $unitOfWork = null;

    /**
     * @var \Pairity\Contracts\Events\DispatcherInterface|null
     */
    protected ?\Pairity\Contracts\Events\DispatcherInterface $dispatcher = null;

    /**
     * DatabaseManager constructor.
     *
     * @param array $config The database configuration.
     * @param \Pairity\Contracts\Container\ContainerInterface|null $container
     */
    public function __construct(
        protected array $config,
        ?\Pairity\Contracts\Container\ContainerInterface $container = null
    ) {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function getDispatcher(): \Pairity\Contracts\Events\DispatcherInterface
    {
        if (!$this->dispatcher) {
            $this->dispatcher = new \Pairity\Events\Dispatcher();
        }

        return $this->dispatcher;
    }

    /**
     * @inheritDoc
     */
    public function setDispatcher(\Pairity\Contracts\Events\DispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Get the Unit of Work instance.
     *
     * @return UnitOfWork
     */
    public function unitOfWork(): UnitOfWork
    {
        if (!$this->unitOfWork) {
            $this->unitOfWork = new UnitOfWork($this);
        }

        return $this->unitOfWork;
    }

    /**
     * Set the container instance.
     *
     * @param \Pairity\Contracts\Container\ContainerInterface $container
     * @return void
     */
    public function setContainer(\Pairity\Contracts\Container\ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): \Pairity\Contracts\Container\ContainerInterface
    {
        if (!$this->container) {
            $translator = new \Pairity\Translation\Translator(__DIR__ . '/../Translations');
            throw new RuntimeException($translator->trans('error.container_not_set'));
        }
        return $this->container;
    }

    /**
     * @inheritDoc
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * @inheritDoc
     */
    public function reconnect(?string $name = null): ConnectionInterface
    {
        $name = $name ?: $this->getDefaultConnection();

        $this->disconnect($name);

        return $this->connection($name);
    }

    /**
     * @inheritDoc
     */
    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }

    /**
     * @inheritDoc
     */
    public function setDefaultConnection(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Resolve a connection instance.
     *
     * @param string $name
     * @return ConnectionInterface
     * @throws RuntimeException
     */
    protected function makeConnection(string $name): ConnectionInterface
    {
        $config = $this->getConnectionConfig($name);
        $driverName = $config['driver'] ?? 'sqlite';
        $driver = $this->resolveDriver($driverName);

        return new Connection($name, $driver, $config);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param string $name
     * @return array
     * @throws RuntimeException
     */
    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->config['connections'] ?? [];

        if (!isset($connections[$name])) {
            throw new RuntimeException("Database connection [{$name}] not configured.");
        }

        return $connections[$name];
    }

    /**
     * Resolve the driver instance.
     *
     * @param string $name
     * @return DriverInterface
     * @throws RuntimeException
     */
    protected function resolveDriver(string $name): DriverInterface
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $driver = match ($name) {
            'sqlite' => new SQLiteDriver(),
            'mysql' => new MySQLDriver(),
            'pgsql', 'postgres' => new PostgresDriver(),
            'sqlsrv', 'sqlserver' => new SqlServerDriver(),
            'oci', 'oracle' => new OracleDriver(),
            default => throw new RuntimeException("Database driver [{$name}] not supported."),
        };

        return $this->drivers[$name] = $driver;
    }

    /**
     * Get the query grammar for a driver.
     *
     * @param string $driver
     * @return \Pairity\Database\Query\Grammar
     */
    public function getQueryGrammar(string $driver): \Pairity\Database\Query\Grammar
    {
        return match ($driver) {
            'mysql' => new \Pairity\Database\Query\Grammars\MySqlGrammar(),
            'pgsql', 'postgres' => new \Pairity\Database\Query\Grammars\PostgresGrammar(),
            'sqlsrv', 'sqlserver' => new \Pairity\Database\Query\Grammars\SqlServerGrammar(),
            'oci', 'oracle' => new \Pairity\Database\Query\Grammars\OracleGrammar(),
            'sqlite' => new \Pairity\Database\Query\Grammars\SqliteGrammar(),
            default => (function() use ($driver) {
                $translator = $this->getContainer()->get(\Pairity\Contracts\Translation\TranslatorInterface::class);
                throw new \Pairity\Exceptions\DatabaseException(
                    $translator->trans('error.driver_not_supported', ['driver' => $driver]),
                    0,
                    null,
                    ['driver' => $driver]
                );
            })(),
        };
    }
}

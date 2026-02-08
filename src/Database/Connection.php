<?php

declare(strict_types=1);

namespace Pairity\Database;

use Pairity\Contracts\Database\ConnectionInterface;
use Pairity\Contracts\Database\DriverInterface;
use Pairity\Contracts\Database\InterceptorInterface;
use Pairity\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Connection
 *
 * Wraps a PDO instance and provides database access methods.
 *
 * @package Pairity\Database
 */
class Connection implements ConnectionInterface
{
    /**
     * @var PDO|null
     */
    protected ?PDO $readPdo = null;

    /**
     * @var PDO|null
     */
    protected ?PDO $writePdo = null;

    /**
     * @var bool Whether a write has occurred during this request.
     */
    protected bool $sticky = false;

    /**
     * @var int The current transaction level.
     */
    protected int $transactions = 0;

    /**
     * @var array<InterceptorInterface> Registered interceptors.
     */
    protected array $interceptors = [];

    /**
     * Connection constructor.
     *
     * @param string $name
     * @param DriverInterface $driver
     * @param array $config
     */
    public function __construct(
        protected string $name,
        protected DriverInterface $driver,
        protected array $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getReadPdo(): PDO
    {
        if ($this->sticky && $this->writePdo) {
            return $this->writePdo;
        }

        if ($this->readPdo === null) {
            $this->readPdo = $this->createPdo($this->getReadConfig());
        }

        return $this->readPdo;
    }

    /**
     * @inheritDoc
     */
    public function getWritePdo(): PDO
    {
        if ($this->writePdo === null) {
            $this->writePdo = $this->createPdo($this->getWriteConfig());
        }

        return $this->writePdo;
    }

    /**
     * Create a new PDO instance from the given config.
     *
     * @param array $config
     * @return PDO
     * @throws QueryException
     */
    protected function createPdo(array $config): PDO
    {
        try {
            return $this->driver->connect($config);
        } catch (PDOException $e) {
            throw new QueryException(
                '',
                [],
                "Failed to connect to database: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the configuration for a read connection.
     *
     * @return array
     */
    protected function getReadConfig(): array
    {
        $config = $this->config['read'] ?? $this->config;

        if (isset($config[0])) {
            return array_merge($this->config, $config[array_rand($config)]);
        }

        return array_merge($this->config, $config);
    }

    /**
     * Get the configuration for a write connection.
     *
     * @return array
     */
    protected function getWriteConfig(): array
    {
        $config = $this->config['write'] ?? $this->config;

        if (isset($config[0])) {
            return array_merge($this->config, $config[array_rand($config)]);
        }

        return array_merge($this->config, $config);
    }

    /**
     * @inheritDoc
     */
    public function execute(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function (PDOStatement $statement) {
            $this->sticky = true;
            return $statement->rowCount();
        }, 'write');
    }

    /**
     * @inheritDoc
     */
    public function select(string $query, array $bindings = []): array
    {
        return $this->run($query, $bindings, function (PDOStatement $statement) {
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }, 'read');
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, array $bindings = []): PDOStatement
    {
        // For raw query, we default to write if we're not sure, 
        // but typically raw queries should be handled carefully.
        // Let's stick with read by default if it's not a modifying query, 
        // but for now let's just use write to be safe or allow user to specify?
        // SPECS say select for read, execute for write. query() is raw.
        // Let's assume raw query is write.
        return $this->run($query, $bindings, function (PDOStatement $statement) {
            return $statement;
        }, 'write');
    }

    /**
     * Run a query and handle exceptions.
     *
     * @param string $query
     * @param array $bindings
     * @param callable $callback
     * @param string $mode
     * @return mixed
     * @throws QueryException
     */
    protected function run(string $query, array $bindings, callable $callback, string $mode = 'write'): mixed
    {
        $pipeline = $this->createPipeline($callback);

        return $pipeline($query, $bindings, $mode);
    }

    /**
     * Create the interceptor pipeline.
     *
     * @param callable $final
     * @return callable
     */
    protected function createPipeline(callable $final): callable
    {
        $pipeline = function (string $query, array $bindings, string $mode) use ($final) {
            try {
                $pdo = ($mode === 'write') ? $this->getWritePdo() : $this->getReadPdo();
                $statement = $pdo->prepare($query);
                $statement->execute($bindings);
                return $final($statement);
            } catch (PDOException $e) {
                throw new QueryException(
                    $query,
                    $bindings,
                    $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        };

        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = $pipeline;
            $pipeline = function (string $query, array $bindings, string $mode) use ($interceptor, $next) {
                return $interceptor->intercept($query, $bindings, $mode, $next);
            };
        }

        return $pipeline;
    }

    /**
     * @inheritDoc
     */
    public function checkHealth(): bool
    {
        try {
            $this->select('SELECT 1');
            return true;
        } catch (PDOException|QueryException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getWritePdo();

        if ($this->transactions === 0) {
            $pdo->beginTransaction();
        } elseif ($this->transactions > 0) {
            $pdo->exec("SAVEPOINT parity_{$this->transactions}");
        }

        $this->transactions++;
        $this->sticky = true;
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->getWritePdo()->commit();
        } elseif ($this->transactions > 1) {
            $this->getWritePdo()->exec("RELEASE SAVEPOINT parity_" . ($this->transactions - 1));
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    /**
     * @inheritDoc
     */
    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            $this->getWritePdo()->rollBack();
        } elseif ($this->transactions > 1) {
            $this->getWritePdo()->exec("ROLLBACK TO SAVEPOINT parity_" . ($this->transactions - 1));
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    /**
     * @inheritDoc
     */
    public function addInterceptor(InterceptorInterface $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }

    /**
     * @inheritDoc
     */
    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Disconnect the PDO instances.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->readPdo = null;
        $this->writePdo = null;
        $this->sticky = false;
    }
}

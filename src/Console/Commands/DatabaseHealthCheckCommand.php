<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class DatabaseHealthCheckCommand
 *
 * CLI command to verify database connection health.
 *
 * @package Pairity\Console\Commands
 */
class DatabaseHealthCheckCommand implements CommandInterface
{
    /**
     * DatabaseHealthCheckCommand constructor.
     *
     * @param DatabaseManagerInterface $db
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected DatabaseManagerInterface $db,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'db:check:health';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.db_health_check.description', 'Verify database connection health and heartbeat.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $connectionName = $args[0] ?? null;

        try {
            $connection = $this->db->connection($connectionName);
            $name = $connection->getName();

            echo $this->t('command.db_health_check.checking', 'Checking health for connection: {name}...', ['name' => $name]) . "\n";

            if ($connection->checkHealth()) {
                echo $this->t('command.db_health_check.success', 'Connection {name} is healthy.', ['name' => $name]) . "\n";
                return 0;
            }

            echo $this->t('command.db_health_check.failed', 'Connection {name} is unhealthy.', ['name' => $name]) . "\n";
            return 1;
        } catch (\Throwable $e) {
            echo $this->t('command.db_health_check.error', 'Error checking health: {message}', ['message' => $e->getMessage()]) . "\n";
            return 1;
        }
    }

    /**
     * Translate a message if a Translator is available.
     *
     * @param string $key
     * @param string $default
     * @param array $replace
     * @return string
     */
    protected function t(string $key, string $default, array $replace = []): string
    {
        if ($this->translator) {
            return $this->translator->trans($key, $replace);
        }

        foreach ($replace as $placeholder => $value) {
            $default = str_replace('{' . $placeholder . '}', (string) $value, $default);
        }

        return $default;
    }
}

<?php

declare(strict_types=1);

namespace Pairity\Console;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Container\ContainerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use RuntimeException;

/**
 * Class Application
 *
 * The main console application for Pairity.
 * Responsible for command registration, parsing arguments, and dispatching execution.
 *
 * @package Pairity\Console
 */
class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    protected array $commands = [];

    /**
     * Application constructor.
     *
     * @param ContainerInterface $container The service container instance.
     * @param string $name The application name.
     * @param string $version The application version.
     */
    public function __construct(
        protected ContainerInterface $container,
        protected string $name = 'Pairity CLI',
        protected string $version = '1.0.0'
    ) {
    }

    /**
     * Register a command with the application.
     *
     * @param string|CommandInterface $command The command class name or instance.
     * @return void
     */
    public function add(string|CommandInterface $command): void
    {
        if (is_string($command)) {
            $command = $this->container->make($command);
        }

        if (!$command instanceof CommandInterface) {
            throw new RuntimeException('Command must implement Pairity\Contracts\Console\CommandInterface');
        }

        $this->commands[$command->getName()] = $command;
    }

    /**
     * Run the application.
     *
     * @param array $argv The raw command line arguments (usually from $_SERVER['argv']).
     * @return int The exit code.
     */
    public function run(array $argv): int
    {
        // Remove the script name from argv
        array_shift($argv);

        if (empty($argv)) {
            $this->printHelp();
            return 0;
        }

        $commandName = array_shift($argv);

        if ($commandName === '--version' || $commandName === '-V') {
            $this->printVersion();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $message = $this->t('error.command_not_found', ['command' => $commandName]);
            fwrite(STDERR, $message . "\n");
            return 1;
        }

        $command = $this->commands[$commandName];

        // Basic argument/option parsing (can be expanded later)
        $args = [];
        $options = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '-')) {
                $options[] = $arg;
            } else {
                $args[] = $arg;
            }
        }

        try {
            return $command->execute($args, $options);
        } catch (\Throwable $e) {
            $message = $this->t('error.uncaught_exception', ['message' => $e->getMessage()]);
            fwrite(STDERR, $message . "\n");
            return 1;
        }
    }

    /**
     * Print the application version information.
     *
     * @return void
     */
    protected function printVersion(): void
    {
        $line = $this->t('app.version', ['name' => $this->name, 'version' => $this->version]);
        echo $line . "\n";
    }

    /**
     * Print the help information including available commands.
     *
     * @return void
     */
    protected function printHelp(): void
    {
        $this->printVersion();
        echo $this->t('app.usage');
        echo $this->t('app.available_commands') . "\n";

        ksort($this->commands);

        foreach ($this->commands as $name => $command) {
            printf("  %-20s %s\n", $name, $command->getDescription());
        }
    }

    /**
     * Translate a message if a Translator is available.
     * Falls back to the key itself or plain text when not available.
     *
     * @param string $key
     * @param array<string, mixed> $replace
     * @return string
     */
    protected function t(string $key, array $replace = []): string
    {
        if ($this->container->has(TranslatorInterface::class)) {
            /** @var TranslatorInterface $translator */
            $translator = $this->container->get(TranslatorInterface::class);
            return $translator->trans($key, $replace);
        }

        // Fallbacks for a few known keys to preserve previous behavior
        return match ($key) {
            'app.usage' => "\nUsage:\n  pairity [command] [arguments] [options]\n\n",
            'app.available_commands' => 'Available commands:',
            'app.version' => ($replace['name'] ?? 'Pairity CLI') . ' version ' . ($replace['version'] ?? '1.0.0'),
            'error.command_not_found' => "Command '" . ($replace['command'] ?? '') . "' not found.",
            'error.uncaught_exception' => 'Uncaught Exception: ' . ($replace['message'] ?? ''),
            default => $key,
        };
    }
}

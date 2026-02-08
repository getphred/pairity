<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class InitCommand
 *
 * CLI command to initialize the Pairity ORM project structure.
 *
 * @package Pairity\Console\Commands
 */
class InitCommand implements CommandInterface
{
    /**
     * InitCommand constructor.
     *
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'init';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.init.description', 'Initialize the Pairity project structure.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $directories = [
            'schema',
            'storage/cache',
            'src/Models/DTO',
            'src/Models/DAO',
            'src/Models/Hydrators',
            'src/Database/Migrations',
            'src/Database/Seeds',
            'src/Database/Factories',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "Created directory: {$dir}\n";
                } else {
                    echo "Failed to create directory: {$dir}\n";
                    return 1;
                }
            } else {
                echo "Directory already exists: {$dir}\n";
            }
        }

        echo "Pairity initialization completed successfully.\n";
        return 0;
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

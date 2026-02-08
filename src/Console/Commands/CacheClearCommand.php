<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Schema\MetadataManager;

/**
 * Class CacheClearCommand
 *
 * CLI command to clear the metadata cache.
 *
 * @package Pairity\Console\Commands
 */
class CacheClearCommand implements CommandInterface
{
    /**
     * CacheClearCommand constructor.
     *
     * @param MetadataManager $metadataManager
     * @param TranslatorInterface|null $translator
     */
    public function __construct(
        protected MetadataManager $metadataManager,
        protected ?TranslatorInterface $translator = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'cache:clear';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->t('command.cache_clear.description', 'Clear the Pairity metadata cache.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        echo $this->t('command.cache_clear.starting', 'Clearing metadata cache...') . "\n";

        if ($this->metadataManager->clearCache()) {
            echo $this->t('command.cache_clear.success', 'Metadata cache cleared successfully.') . "\n";
            return 0;
        }

        echo $this->t('command.cache_clear.error', 'Failed to clear metadata cache.') . "\n";
        return 1;
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

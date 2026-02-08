<?php

declare(strict_types=1);

namespace Pairity\Translation;

use Pairity\Contracts\Translation\TranslatorInterface;
use RuntimeException;

/**
 * Class Translator
 *
 * Handles localization for the Pairity ORM.
 * Loads translations from PHP files in the src/Translations directory.
 *
 * @package Pairity\Translation
 */
class Translator implements TranslatorInterface
{
    /**
     * @var array<string, array<string, string>> Loaded translations indexed by locale.
     */
    protected array $translations = [];

    /**
     * @var string The current locale.
     */
    protected string $locale;

    /**
     * Translator constructor.
     *
     * @param string $translationsPath The path to the translations directory.
     * @param string $defaultLocale The default locale to use.
     */
    public function __construct(
        protected string $translationsPath,
        string $defaultLocale = 'en'
    ) {
        $this->locale = getenv('PAIRITY_LOCALE') ?: $defaultLocale;
    }

    /**
     * @inheritDoc
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->locale;

        $this->loadLocale($locale);

        $message = $this->translations[$locale][$key] ?? $key;

        if (empty($replace)) {
            return $message;
        }

        foreach ($replace as $placeholder => $value) {
            $message = str_replace('{' . $placeholder . '}', (string) $value, $message);
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @inheritDoc
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Load the translation file for the given locale.
     *
     * @param string $locale
     * @return void
     * @throws RuntimeException If the translation file cannot be found.
     */
    protected function loadLocale(string $locale): void
    {
        if (isset($this->translations[$locale])) {
            return;
        }

        $filePath = rtrim($this->translationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $locale . '.php';

        if (!file_exists($filePath)) {
            // Fallback to English if the requested locale is not found
            if ($locale !== 'en') {
                $this->loadLocale('en');
                $this->translations[$locale] = $this->translations['en'];
                return;
            }
            
            throw new RuntimeException("Translation file for locale '{$locale}' not found at '{$filePath}'.");
        }

        $translations = include $filePath;

        if (!is_array($translations)) {
            throw new RuntimeException("Translation file '{$filePath}' must return an array.");
        }

        $this->translations[$locale] = $translations;
    }
}

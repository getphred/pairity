<?php

declare(strict_types=1);

namespace Pairity\Contracts\Translation;

/**
 * Interface TranslatorInterface
 *
 * Defines the contract for the Pairity Translation service.
 *
 * @package Pairity\Contracts\Translation
 */
interface TranslatorInterface
{
    /**
     * Translate the given message.
     *
     * @param string $key The translation key.
     * @param array<string, mixed> $replace The values to replace in the message.
     * @param string|null $locale The locale to use (defaults to the current locale).
     * @return string The translated message.
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string;

    /**
     * Get the current locale.
     *
     * @return string
     */
    public function getLocale(): string;

    /**
     * Set the current locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale): void;
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Translation;

use Pairity\Contracts\Translation\TranslatorInterface;
use Pairity\Translation\Translator;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
    private string $translationsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationsPath = __DIR__ . '/../../../src/Translations';
    }

    public function test_translates_known_key(): void
    {
        $t = new Translator($this->translationsPath, 'en');
        $this->assertSame('Available commands:', $t->trans('app.available_commands'));
    }

    public function test_placeholder_replacement(): void
    {
        $t = new Translator($this->translationsPath, 'en');
        $res = $t->trans('app.version', ['name' => 'Pairity CLI', 'version' => '1.2.3']);
        $this->assertSame('Pairity CLI version 1.2.3', $res);
    }

    public function test_fallback_to_key_when_missing(): void
    {
        $t = new Translator($this->translationsPath, 'en');
        $this->assertSame('missing.key', $t->trans('missing.key'));
    }

    public function test_env_locale_is_used(): void
    {
        $t = new Translator($this->translationsPath, 'en');
        $this->assertSame('en', $t->getLocale());
        $t->setLocale('en');
        $this->assertSame('Available commands:', $t->trans('app.available_commands'));
    }
}

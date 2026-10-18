<?php

use LcmtDevMailer\RequestLanguage;
use PHPUnit\Framework\TestCase;

class RequestLanguageTest extends TestCase
{
    private const LANGUAGES = ['fr' => 'fr_FR', 'en' => 'en_GB'];

    public function test_known_language_gives_its_locale(): void
    {
        $this->assertSame('en_GB', RequestLanguage::locale('en', self::LANGUAGES));
        $this->assertSame('fr_FR', RequestLanguage::locale('fr', self::LANGUAGES));
    }

    public function test_unknown_or_empty_language_gives_null(): void
    {
        foreach (['de', '', null, 'EN', '0'] as $requested) {
            $this->assertNull(RequestLanguage::locale($requested, self::LANGUAGES), var_export($requested, true));
        }

        $this->assertNull(RequestLanguage::locale('en', []));
    }

    public function test_without_polylang_there_is_no_current_language(): void
    {
        $this->assertNull(RequestLanguage::current());
    }

    public function test_using_sets_the_current_language_for_a_callback_only(): void
    {
        $seen = RequestLanguage::using('en', static fn() => RequestLanguage::current());

        $this->assertSame('en', $seen);
        $this->assertNull(RequestLanguage::current());
        $this->assertNull(RequestLanguage::using('', static fn() => RequestLanguage::current()));
    }

    public function test_using_restores_the_language_when_the_callback_throws(): void
    {
        try {
            RequestLanguage::using('en', static function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
        }

        $this->assertNull(RequestLanguage::current());
    }
}

<?php

use LcmtDevMailer\AdminMailSender;
use LcmtDevMailer\Altcha;
use LcmtDevMailer\Captcha;
use LcmtDevMailer\CaptchaProvider;
use LcmtDevMailer\SecretBox;
use LcmtDevMailer\Turnstile;
use PHPUnit\Framework\TestCase;

/**
 * A provider added through the filter, without CaptchaBadge.
 */
class PlainBadgeProvider implements CaptchaProvider
{
    public static function id(): string
    {
        return 'plain';
    }

    public static function label(): string
    {
        return 'Plain <b>';
    }

    public static function isReady(): bool
    {
        return true;
    }

    public static function registerRoutes(): void {}

    public static function widget(): string
    {
        return '';
    }

    public static function verify(array $body): bool
    {
        return true;
    }

    public static function renderSettings(): void {}
}

class ProtectionBadgeTest extends TestCase
{
    private const POST = 9;

    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']    = [Captcha::OPTION => 'altcha', Altcha::OPTION_KEY => SecretBox::encrypt('hmac')];
        $GLOBALS['lcmt_test_transients'] = [];
        $GLOBALS['lcmt_test_post_meta']  = [];
        $GLOBALS['lcmt_test_filters']    = [];
    }

    private function choose(string $value): void
    {
        $GLOBALS['lcmt_test_post_meta'][self::POST]['_lcmt_mail_captcha'] = $value;
    }

    private function setUpTurnstile(): void
    {
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SITE_KEY]   = 'site';
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = SecretBox::encrypt('secret');
    }

    private static function badge(string $icon, string $color, string $label, bool $muted = false): array
    {
        return compact('icon', 'color', 'label', 'muted');
    }

    public function test_turnstile_active(): void
    {
        $this->setUpTurnstile();
        $this->choose('turnstile');

        $this->assertSame(self::badge('dashicons-shield', '#00a32a', 'Cloudflare Turnstile'), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_altcha_active(): void
    {
        $this->choose('altcha');

        $this->assertSame(self::badge('dashicons-shield-alt', '#2271b1', 'ALTCHA'), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_inherited_site_default_is_muted(): void
    {
        $this->assertSame(self::badge('dashicons-shield-alt', '#2271b1', 'Site default (ALTCHA)', true), AdminMailSender::protectionBadge(self::POST));

        $this->setUpTurnstile();
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'turnstile';

        $this->assertSame(self::badge('dashicons-shield', '#00a32a', 'Site default (Cloudflare Turnstile)', true), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_choice_not_set_up_falling_back_to_the_site_default(): void
    {
        $this->choose('turnstile');

        $this->assertSame(self::badge('dashicons-shield-alt', '#2271b1', 'Site default (ALTCHA)', true), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_not_set_up_and_nothing_active(): void
    {
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'none';
        $this->choose('turnstile');

        $this->assertSame(self::badge('dashicons-warning', '#dba617', 'Cloudflare Turnstile — not set up'), AdminMailSender::protectionBadge(self::POST));

        // Inherited from a site default that is not set up either
        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'turnstile';
        $this->choose('');

        $this->assertSame(self::badge('dashicons-warning', '#dba617', 'Cloudflare Turnstile — not set up'), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_not_protected(): void
    {
        $this->choose('none');

        $this->assertSame(self::badge('dashicons-dismiss', '#d63638', 'Not protected'), AdminMailSender::protectionBadge(self::POST));

        $GLOBALS['lcmt_test_options'][Captcha::OPTION] = 'none';
        $this->choose('');

        $this->assertSame(self::badge('dashicons-dismiss', '#d63638', 'Not protected'), AdminMailSender::protectionBadge(self::POST));
    }

    public function test_provider_without_badge_gets_the_neutral_one(): void
    {
        $GLOBALS['lcmt_test_filters']['lcmt_mailer_captcha_providers'] = ['plain' => PlainBadgeProvider::class];
        $this->choose('plain');

        // The label stays raw here: the column escapes it
        $this->assertSame(self::badge('dashicons-shield', '#50575e', 'Plain <b>'), AdminMailSender::protectionBadge(self::POST));
    }
}

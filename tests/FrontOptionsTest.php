<?php

use LcmtDevMailer\Altcha;
use LcmtDevMailer\Attribution;
use LcmtDevMailer\Captcha;
use LcmtDevMailer\FormRenderer;
use LcmtDevMailer\Settings;
use LcmtDevMailer\Turnstile;
use PHPUnit\Framework\TestCase;

/**
 * None of the options a public page reads is autoloaded: each one left out of
 * FormRenderer::frontOptions() costs a database query of its own.
 */
class FrontOptionsTest extends TestCase
{
    public function test_the_alert_colors_are_fetched_with_the_other_options(): void
    {
        foreach ([
            Settings::OPTION_COLOR_SUCCESS,
            Settings::OPTION_COLOR_ERROR,
            Settings::OPTION_TEXT_SUCCESS,
            Settings::OPTION_TEXT_ERROR,
        ] as $option) {
            $this->assertContains($option, FormRenderer::frontOptions(), $option);
        }
    }

    public function test_the_spam_protection_and_attribution_options_are_still_fetched(): void
    {
        foreach ([
            Attribution::OPTION_WITHOUT_CONSENT,
            Captcha::OPTION,
            Altcha::OPTION_KEY,
            Turnstile::OPTION_SITE_KEY,
            Turnstile::OPTION_SECRET_KEY,
            Turnstile::OPTION_APPEARANCE,
        ] as $option) {
            $this->assertContains($option, FormRenderer::frontOptions(), $option);
        }
    }
}

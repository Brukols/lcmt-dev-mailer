<?php

use LcmtDevMailer\ChannelClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChannelClassifierTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'gclid'                     => ['google_ads', ['click_id_type' => 'gclid']],
            'gbraid'                    => ['google_ads', ['click_id_type' => 'gbraid']],
            'google cpc without gclid'  => ['google_ads', ['utm_source' => 'google', 'utm_medium' => 'cpc']],
            'meta ads'                  => ['paid_social', ['utm_source' => 'fb', 'utm_medium' => 'paid_social']],
            'tiktok click'              => ['paid_social', ['click_id_type' => 'ttclid']],
            'bing ads'                  => ['paid_other', ['click_id_type' => 'msclkid']],
            'other paid medium'         => ['paid_other', ['utm_source' => 'partner', 'utm_medium' => 'display']],
            'newsletter'                => ['email', ['utm_source' => 'brevo', 'utm_medium' => 'email']],
            'fbclid on organic share'   => ['social', ['click_id_type' => 'fbclid', 'referrer_host' => 'm.facebook.com']],
            'instagram referrer'        => ['social', ['referrer_host' => 'l.instagram.com']],
            'linkedin short link'       => ['social', ['referrer_host' => 'lnkd.in']],
            'google search'             => ['organic_search', ['referrer_host' => 'google.fr']],
            'bing search'               => ['organic_search', ['referrer_host' => 'bing.com']],
            'qwant'                     => ['organic_search', ['referrer_host' => 'qwant.com']],
            'brave search'              => ['organic_search', ['referrer_host' => 'search.brave.com']],
            'unknown utm source'        => ['campaign', ['utm_source' => 'flyer-salon-2026']],
            'other site'                => ['referral', ['referrer_host' => 'mariages.net']],
            'nothing'                   => ['direct', []],
            'lookalike host'            => ['referral', ['referrer_host' => 'notgoogle.com']],
        ];
    }

    #[DataProvider('cases')]
    public function testClassifies(string $expected, array $context): void
    {
        $this->assertSame($expected, ChannelClassifier::classify($context));
    }

    public function testLabelsEveryChannel(): void
    {
        foreach (ChannelClassifier::CHANNELS as $channel) {
            $this->assertNotSame($channel, ChannelClassifier::label($channel), $channel);
        }

        $this->assertSame('unknown', ChannelClassifier::label('unknown'));
    }
}

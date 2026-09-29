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
            'claude referrer'           => ['ai_assistant', ['referrer_host' => 'claude.ai']],
            'chatgpt referrer'          => ['ai_assistant', ['referrer_host' => 'chatgpt.com']],
            'chat openai referrer'      => ['ai_assistant', ['referrer_host' => 'chat.openai.com']],
            'openai referrer'           => ['ai_assistant', ['referrer_host' => 'openai.com']],
            'perplexity referrer'       => ['ai_assistant', ['referrer_host' => 'perplexity.ai']],
            'www perplexity'            => ['ai_assistant', ['referrer_host' => 'www.perplexity.ai']],
            'gemini referrer'           => ['ai_assistant', ['referrer_host' => 'gemini.google.com']],
            'bard referrer'             => ['ai_assistant', ['referrer_host' => 'bard.google.com']],
            'copilot referrer'          => ['ai_assistant', ['referrer_host' => 'copilot.microsoft.com']],
            'copilot cloud referrer'    => ['ai_assistant', ['referrer_host' => 'copilot.cloud.microsoft']],
            'le chat referrer'          => ['ai_assistant', ['referrer_host' => 'chat.mistral.ai']],
            'mistral referrer'          => ['ai_assistant', ['referrer_host' => 'mistral.ai']],
            'deepseek referrer'         => ['ai_assistant', ['referrer_host' => 'deepseek.com']],
            'chat deepseek referrer'    => ['ai_assistant', ['referrer_host' => 'chat.deepseek.com']],
            'you.com referrer'          => ['ai_assistant', ['referrer_host' => 'you.com']],
            'meta ai referrer'          => ['ai_assistant', ['referrer_host' => 'meta.ai']],
            'grok referrer'             => ['ai_assistant', ['referrer_host' => 'grok.com']],
            'x.ai referrer'             => ['ai_assistant', ['referrer_host' => 'x.ai']],
            'subdomain of an AI host'   => ['ai_assistant', ['referrer_host' => 'www.claude.ai']],
            'box.ai is not x.ai'        => ['referral', ['referrer_host' => 'box.ai']],
            'x.com stays social'        => ['social', ['referrer_host' => 'x.com']],
            'google.com stays search'   => ['organic_search', ['referrer_host' => 'google.com']],
            'www.google.com stays search' => ['organic_search', ['referrer_host' => 'www.google.com']],
            'google.fr stays search'    => ['organic_search', ['referrer_host' => 'www.google.fr']],
            'utm chatgpt.com'           => ['ai_assistant', ['utm_source' => 'chatgpt.com']],
            'utm chatgpt'               => ['ai_assistant', ['utm_source' => 'chatgpt']],
            'utm openai'                => ['ai_assistant', ['utm_source' => 'openai']],
            'utm claude'                => ['ai_assistant', ['utm_source' => 'claude']],
            'utm claude.ai'             => ['ai_assistant', ['utm_source' => 'claude.ai']],
            'utm perplexity'            => ['ai_assistant', ['utm_source' => 'perplexity']],
            'utm perplexity.ai'         => ['ai_assistant', ['utm_source' => 'perplexity.ai']],
            'utm gemini'                => ['ai_assistant', ['utm_source' => 'gemini']],
            'utm copilot'               => ['ai_assistant', ['utm_source' => 'copilot']],
            'utm mistral'               => ['ai_assistant', ['utm_source' => 'mistral']],
            'utm deepseek'              => ['ai_assistant', ['utm_source' => 'deepseek']],
            'utm source is case-insensitive' => ['ai_assistant', ['utm_source' => 'ChatGPT']],
            'utm source is exact'       => ['campaign', ['utm_source' => 'claude-newsletter']],
            'gclid on an AI referrer'   => ['google_ads', ['click_id_type' => 'gclid', 'referrer_host' => 'chatgpt.com']],
            'paid medium on an AI source' => ['paid_other', ['utm_source' => 'chatgpt.com', 'utm_medium' => 'cpc']],
            'email medium on an AI source' => ['email', ['utm_source' => 'claude', 'utm_medium' => 'email']],
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

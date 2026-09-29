<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sorts a submission into the channel that brought the visitor.
 *
 * Paid signals win over everything (an ad click that went through a search
 * page is still an ad), then explicit campaign mediums, then AI assistants, then the referrer.
 */
class ChannelClassifier
{
    public const CHANNELS = [
        'google_ads', 'paid_social', 'paid_other', 'email', 'ai_assistant',
        'social', 'organic_search', 'campaign', 'referral', 'direct',
    ];

    private const SEARCH_ENGINES = [
        'google', 'bing', 'yahoo', 'duckduckgo', 'qwant', 'ecosia',
        'yandex', 'baidu', 'startpage', 'lilo', 'search.brave.com',
    ];

    /**
     * Dotted names: the host or its subdomains ("box.ai" is not "x.ai").
     */
    private const AI_HOSTS = [
        'claude.ai', 'chatgpt.com', 'chat.openai.com', 'openai.com', 'perplexity.ai',
        'gemini.google.com', 'bard.google.com', 'copilot.microsoft.com', 'copilot.cloud.microsoft',
        'chat.mistral.ai', 'mistral.ai', 'deepseek.com', 'chat.deepseek.com', 'you.com',
        'meta.ai', 'grok.com', 'x.ai',
    ];

    /**
     * utm_source values (already lowercased), matched exactly.
     */
    private const AI_SOURCES = [
        'chatgpt.com', 'chatgpt', 'openai', 'claude', 'claude.ai', 'perplexity',
        'perplexity.ai', 'gemini', 'copilot', 'mistral', 'deepseek',
    ];

    private const SOCIAL_NETWORKS = [
        'facebook', 'fb', 'instagram', 'ig', 'linkedin', 'lnkd.in', 'twitter',
        't.co', 'x.com', 'pinterest', 'youtube', 'tiktok', 'threads.net',
        'reddit', 'snapchat',
    ];

    /**
     * @param array<string, mixed> $context SubmissionContext::fromRequest() output.
     */
    public static function classify(array $context): string
    {
        $click  = (string) ($context['click_id_type'] ?? '');
        $source = strtolower((string) ($context['utm_source'] ?? ''));
        $medium = strtolower((string) ($context['utm_medium'] ?? ''));
        $host   = strtolower((string) ($context['referrer_host'] ?? ''));

        $paid = (bool) preg_match('/^(cpc|ppc|cpm|display|banner|paid.*)$/', $medium);

        if (in_array($click, ['gclid', 'gbraid', 'wbraid'], true) || ($paid && $source === 'google')) {
            return 'google_ads';
        }

        if (in_array($click, ['ttclid', 'li_fat_id'], true) || ($paid && self::matches($source, self::SOCIAL_NETWORKS))) {
            return 'paid_social';
        }

        if ($click === 'msclkid' || $paid) {
            return 'paid_other';
        }

        if (in_array($medium, ['email', 'e-mail', 'newsletter'], true)) {
            return 'email';
        }

        // Before social and search: gemini.google.com would otherwise count as Google search.
        if (in_array($source, self::AI_SOURCES, true) || self::matches($host, self::AI_HOSTS)) {
            return 'ai_assistant';
        }

        // fbclid is added to every outgoing Facebook link, ads or not.
        if ($medium === 'social' || $click === 'fbclid'
            || self::matches($source, self::SOCIAL_NETWORKS) || self::matches($host, self::SOCIAL_NETWORKS)) {
            return 'social';
        }

        if ($medium === 'organic' || self::matches($host, self::SEARCH_ENGINES)) {
            return 'organic_search';
        }

        if ($source !== '') {
            return 'campaign';
        }

        return $host !== '' ? 'referral' : 'direct';
    }

    public static function label(string $channel): string
    {
        return match ($channel) {
            'google_ads'     => __('Google Ads', 'lcmt-dev-mailer'),
            'paid_social'    => __('Paid social', 'lcmt-dev-mailer'),
            'paid_other'     => __('Other ads', 'lcmt-dev-mailer'),
            'email'          => __('Email', 'lcmt-dev-mailer'),
            'ai_assistant'   => __('AI assistants', 'lcmt-dev-mailer'),
            'social'         => __('Social networks', 'lcmt-dev-mailer'),
            'organic_search' => __('Organic search', 'lcmt-dev-mailer'),
            'campaign'       => __('Other campaign', 'lcmt-dev-mailer'),
            'referral'       => __('Other website', 'lcmt-dev-mailer'),
            'direct'         => __('Direct', 'lcmt-dev-mailer'),
            default          => $channel,
        };
    }

    /**
     * Whether a source or host is one of the names: a plain name matches a
     * whole domain label ("google" in "www.google.fr", not in "notgoogle.com"),
     * a dotted name matches the host or its subdomains.
     *
     * @param list<string> $names
     */
    private static function matches(string $value, array $names): bool
    {
        if ($value === '') {
            return false;
        }

        foreach ($names as $name) {
            if ($value === $name) {
                return true;
            }

            $matched = str_contains($name, '.')
                ? str_ends_with($value, '.' . $name)
                : (bool) preg_match('/(^|\.)' . preg_quote($name, '/') . '\./', $value);

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}

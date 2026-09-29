<?php

use LcmtDevMailer\SubmissionContext;
use PHPUnit\Framework\TestCase;

class SubmissionContextTest extends TestCase
{
    private const SITE = 'www.live-decor.fr';

    private function context(array $raw): array
    {
        return SubmissionContext::fromRequest($raw, self::SITE);
    }

    public function testKeepsAWellFormedContext(): void
    {
        $this->assertSame([
            'page_path'     => '/contact/',
            'landing_path'  => '/location-decor/',
            'referrer_host' => 'google.fr',
            'utm_source'    => 'google',
            'utm_medium'    => 'cpc',
            'utm_campaign'  => 'Mariage été 2026',
            'click_id_type' => 'gclid',
            'device'        => 'mobile',
            'locale'        => 'fr-FR',
            'form_seconds'  => 42,
        ], $this->context([
            'page'    => '/contact/?email=jeanne@example.com#top',
            'landing' => [
                'path'         => '/location-decor/',
                'referrer'     => 'https://www.google.fr/',
                'utm_source'   => 'Google',
                'utm_medium'   => 'CPC',
                'utm_campaign' => 'Mariage été 2026',
                'click_id'     => 'gclid',
            ],
            'device'  => 'mobile',
            'locale'  => 'fr-FR',
            'seconds' => 42,
        ]));
    }

    public function testReturnsEmptyValuesForAMissingContext(): void
    {
        foreach ([null, 'junk', 42, []] as $raw) {
            $context = SubmissionContext::fromRequest($raw, self::SITE);

            $this->assertSame('', $context['page_path']);
            $this->assertSame('', $context['referrer_host']);
            $this->assertNull($context['form_seconds']);
            $this->assertCount(10, $context);
        }
    }

    public function testRejectsPathsThatAreNotSitePaths(): void
    {
        foreach (['javascript:alert(1)', '//evil.com/x', 'https://evil.com/', 'contact', '/a b', ['/x']] as $page) {
            $this->assertSame('', $this->context(['page' => $page])['page_path'], var_export($page, true));
        }
    }

    public function testCutsOversizedValues(): void
    {
        $context = $this->context([
            'page'    => '/' . str_repeat('a', 5000),
            'landing' => ['utm_campaign' => str_repeat('é', 5000)],
        ]);

        $this->assertSame(255, strlen($context['page_path']));
        $this->assertSame(150, mb_strlen($context['utm_campaign']));
    }

    public function testDropsTagsQuotesAndBrokenUtf8FromCampaigns(): void
    {
        $context = $this->context(['landing' => [
            'utm_source'   => '<script>"x"</script>',
            'utm_campaign' => "\xC3\x28",
        ]]);

        $this->assertSame('scriptx/script', $context['utm_source']);
        $this->assertSame('', $context['utm_campaign']);
    }

    public function testTreatsTheSiteItselfAsNoReferrer(): void
    {
        foreach (['https://www.live-decor.fr/a', 'http://live-decor.fr/', 'https://LIVE-DECOR.fr'] as $referrer) {
            $this->assertSame('', $this->context(['landing' => ['referrer' => $referrer]])['referrer_host'], $referrer);
        }
    }

    public function testKeepsOnlyTheHostOfAnExternalReferrer(): void
    {
        $context = $this->context(['landing' => ['referrer' => 'https://m.facebook.com/story.php?id=123']]);

        $this->assertSame('m.facebook.com', $context['referrer_host']);
    }

    public function testIgnoresReferrersThatAreNotWebPages(): void
    {
        foreach (['android-app://com.google.android.gm', 'not a url', 'ftp://files.example.com'] as $referrer) {
            $this->assertSame('', $this->context(['landing' => ['referrer' => $referrer]])['referrer_host'], $referrer);
        }
    }

    public function testAcceptsOnlyKnownClickIdsDevicesAndLocales(): void
    {
        $context = $this->context([
            'landing' => ['click_id' => 'evil'],
            'device'  => 'fridge',
            'locale'  => 'fr_FR"><',
        ]);

        $this->assertSame('', $context['click_id_type']);
        $this->assertSame('', $context['device']);
        $this->assertSame('', $context['locale']);
    }

    public function testKeepsFormTimeWithinADay(): void
    {
        $this->assertSame(0, $this->context(['seconds' => '0'])['form_seconds']);
        $this->assertNull($this->context(['seconds' => -5])['form_seconds']);
        $this->assertNull($this->context(['seconds' => 999999])['form_seconds']);
        $this->assertNull($this->context(['seconds' => 'soon'])['form_seconds']);
    }
}

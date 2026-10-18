<?php

use LcmtDevMailer\MailLanguage;
use PHPUnit\Framework\TestCase;

class MailLanguageTest extends TestCase
{
    private const SUBJECT = 'Nouvelle demande de [email]';
    private const CONTENT = "Nom : [fullname*]\nMessage : [message textarea]";

    private function translation(string $subject, string $content, bool $published = true): array
    {
        return ['subject' => $subject, 'content' => $content, 'published' => $published];
    }

    public function test_published_translation_is_used(): void
    {
        $picked = MailLanguage::pick(self::SUBJECT, self::CONTENT, $this->translation(
            'New request from [email]',
            "Name: [fullname*]\nMessage: [message textarea]"
        ));

        $this->assertSame([
            'subject'    => 'New request from [email]',
            'content'    => "Name: [fullname*]\nMessage: [message textarea]",
            'translated' => true,
        ], $picked);
    }

    public function test_no_translation_falls_back_to_the_source(): void
    {
        $this->assertSame(
            ['subject' => self::SUBJECT, 'content' => self::CONTENT, 'translated' => false],
            MailLanguage::pick(self::SUBJECT, self::CONTENT, null)
        );
    }

    public function test_draft_translation_falls_back_to_the_source(): void
    {
        $picked = MailLanguage::pick(self::SUBJECT, self::CONTENT, $this->translation(
            'New request from [email]',
            "Name: [fullname*]\nMessage: [message textarea]",
            false
        ));

        $this->assertSame(['subject' => self::SUBJECT, 'content' => self::CONTENT, 'translated' => false], $picked);
    }

    public function test_translation_with_other_placeholders_falls_back_to_the_source(): void
    {
        $picked = MailLanguage::pick('Demande', 'Nom : [fullname*]', $this->translation('Request', 'Name: [name*]'));

        $this->assertSame(['subject' => 'Demande', 'content' => 'Nom : [fullname*]', 'translated' => false], $picked);
    }

    public function test_translation_that_lost_a_placeholder_falls_back_to_the_source(): void
    {
        $picked = MailLanguage::pick(self::SUBJECT, self::CONTENT, $this->translation(
            'New request from [email]',
            'Name: [fullname*]'
        ));

        $this->assertFalse($picked['translated']);
        $this->assertSame(self::CONTENT, $picked['content']);
    }

    public function test_empty_translated_subject_keeps_the_source_subject(): void
    {
        $picked = MailLanguage::pick(self::SUBJECT, self::CONTENT, $this->translation(
            '',
            "Name: [fullname*]\nMessage: [message textarea]"
        ));

        $this->assertSame([
            'subject'    => self::SUBJECT,
            'content'    => "Name: [fullname*]\nMessage: [message textarea]",
            'translated' => true,
        ], $picked);
    }

    public function test_empty_translation_falls_back_to_the_source(): void
    {
        $picked = MailLanguage::pick(self::SUBJECT, self::CONTENT, $this->translation('', ''));

        $this->assertSame(['subject' => self::SUBJECT, 'content' => self::CONTENT, 'translated' => false], $picked);
    }
}

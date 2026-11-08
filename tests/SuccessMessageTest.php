<?php

use LcmtDevMailer\MetaFields;
use LcmtDevMailer\Settings;
use LcmtDevMailer\SuccessMessage;
use PHPUnit\Framework\TestCase;

/**
 * The message shown after a successful submission: the form's own message
 * (in the submission language when its translation has one), else the site's.
 */
class SuccessMessageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options']   = [];
        $GLOBALS['lcmt_test_post_meta'] = [];
    }

    private function formMessage(int $postId, string $message): void
    {
        $GLOBALS['lcmt_test_post_meta'][$postId][MetaFields::FIELD_SUCCESS_MESSAGE] = $message;
    }

    public function test_the_first_message_set_wins(): void
    {
        $this->assertSame('Translation', SuccessMessage::pick('Translation', 'Form', 'Site'));
        $this->assertSame('Form', SuccessMessage::pick('', 'Form', 'Site'));
        $this->assertSame('Site', SuccessMessage::pick('', '', 'Site'));
    }

    public function test_blank_messages_do_not_count(): void
    {
        $this->assertSame('Site', SuccessMessage::pick("  \n", ' ', 'Site'));
        $this->assertSame('Form', SuccessMessage::pick('', '  Form  ', 'Site'));
    }

    public function test_a_form_shows_its_own_message(): void
    {
        $GLOBALS['lcmt_test_options'][Settings::OPTION_SUCCESS_MESSAGE] = 'Site message';
        $this->formMessage(10, 'Thanks, we answer within 24 hours.');

        $this->assertSame('Thanks, we answer within 24 hours.', SuccessMessage::message(10, 0));
    }

    public function test_a_form_without_a_message_shows_the_site_message(): void
    {
        $GLOBALS['lcmt_test_options'][Settings::OPTION_SUCCESS_MESSAGE] = 'Site message';

        $this->assertSame('Site message', SuccessMessage::message(10, 0));
    }

    public function test_without_any_message_the_default_one_shows(): void
    {
        $this->assertSame('Email sent successfully.', SuccessMessage::message(10, 0));
    }

    public function test_the_translation_message_is_preferred(): void
    {
        $this->formMessage(10, 'Merci !');
        $this->formMessage(20, 'Thank you!');

        $this->assertSame('Thank you!', SuccessMessage::message(10, 20));
    }

    public function test_a_translation_without_a_message_falls_back_to_the_form(): void
    {
        $this->formMessage(10, 'Merci !');

        $this->assertSame('Merci !', SuccessMessage::message(10, 20));
    }
}

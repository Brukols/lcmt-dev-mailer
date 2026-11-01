<?php

use LcmtDevMailer\SecretBox;
use LcmtDevMailer\Turnstile;
use LcmtDevMailer\UnreadableSecrets;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UnreadableSecretsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['lcmt_test_options'] = ['admin_email' => 'admin@example.test'];
        $GLOBALS['lcmt_test_mails']   = [];
    }

    public function test_report_sends_one_mail_to_admin_email_however_many_times_called(): void
    {
        UnreadableSecrets::report('opt_a', 'Key A');
        UnreadableSecrets::report('opt_a', 'Key A');
        UnreadableSecrets::report('opt_a', 'Key A');

        $this->assertCount(1, $GLOBALS['lcmt_test_mails']);
        $mail = $GLOBALS['lcmt_test_mails'][0];
        $this->assertSame('admin@example.test', $mail['to']);
        $this->assertStringContainsString('Test & site', $mail['subject']);
        $this->assertStringContainsString('Test & site', $mail['message']);
        $this->assertStringContainsString('Key A', $mail['message']);
        $this->assertStringContainsString('lcmt-mailer-spam-protection', $mail['message']);
    }

    public function test_each_option_gets_its_own_mail(): void
    {
        UnreadableSecrets::report('opt_a', 'Key A');
        UnreadableSecrets::report('opt_b', 'Key B');

        $this->assertCount(2, $GLOBALS['lcmt_test_mails']);
        $this->assertSame(['opt_a' => 'Key A', 'opt_b' => 'Key B'], $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]);
    }

    public function test_writing_the_option_again_clears_the_report_and_the_lock(): void
    {
        UnreadableSecrets::report('opt_a', 'Key A');
        SecretBox::writeOption('opt_a', 'fresh');

        $this->assertArrayNotHasKey(UnreadableSecrets::OPTION, $GLOBALS['lcmt_test_options']);
        $this->assertArrayNotHasKey('lcmt_mailer_unreadable_mail_opt_a', $GLOBALS['lcmt_test_options']);
        $this->assertSame('fresh', SecretBox::readOption('opt_a', 'Key A'));

        // Broken again later: a new mail is allowed
        UnreadableSecrets::report('opt_a', 'Key A');
        $this->assertCount(2, $GLOBALS['lcmt_test_mails']);
    }

    public function test_unreadable_entry_stays_while_no_constant_replaces_it(): void
    {
        UnreadableSecrets::replacedByConstant(Turnstile::OPTION_SECRET_KEY, Turnstile::CONST_SECRET_KEY);
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = 'lcmt1:unreadable';
        UnreadableSecrets::report(Turnstile::OPTION_SECRET_KEY, 'Turnstile secret');
        UnreadableSecrets::report('opt_b', 'Key B');

        UnreadableSecrets::resolveReplaced();

        $this->assertSame(
            [Turnstile::OPTION_SECRET_KEY => 'Turnstile secret', 'opt_b' => 'Key B'],
            $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]
        );
        $this->assertSame('lcmt1:unreadable', $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY]);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unreadable_entry_is_cleared_once_the_constant_is_set(): void
    {
        UnreadableSecrets::replacedByConstant(Turnstile::OPTION_SECRET_KEY, Turnstile::CONST_SECRET_KEY);
        $GLOBALS['lcmt_test_options'][Turnstile::OPTION_SECRET_KEY] = 'lcmt1:unreadable';
        UnreadableSecrets::report(Turnstile::OPTION_SECRET_KEY, 'Turnstile secret');
        UnreadableSecrets::report('opt_b', 'Key B');

        define(Turnstile::CONST_SECRET_KEY, '1x0000000000000000000000000000000AA');
        UnreadableSecrets::resolveReplaced();

        // Only the replaced key is forgotten, with its stale copy and mail lock
        $this->assertSame(['opt_b' => 'Key B'], $GLOBALS['lcmt_test_options'][UnreadableSecrets::OPTION]);
        $this->assertArrayNotHasKey(Turnstile::OPTION_SECRET_KEY, $GLOBALS['lcmt_test_options']);
        $this->assertArrayNotHasKey('lcmt_mailer_unreadable_mail_' . Turnstile::OPTION_SECRET_KEY, $GLOBALS['lcmt_test_options']);
        $this->assertSame('constant', Turnstile::keySource('secret'));
    }
}

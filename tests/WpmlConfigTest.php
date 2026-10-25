<?php

use LcmtDevMailer\MetaFields;
use PHPUnit\Framework\TestCase;

/**
 * wpml-config.xml tells Polylang and WPML what to translate. The meta names it
 * lists must stay those of MetaFields, so a rename in the code breaks here.
 */
class WpmlConfigTest extends TestCase
{
    private const FILE = __DIR__ . '/../wpml-config.xml';

    private function config(): \SimpleXMLElement
    {
        $this->assertFileExists(self::FILE);

        $xml = simplexml_load_file(self::FILE);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);

        return $xml;
    }

    /**
     * @return list<string>
     */
    private function fields(string $action): array
    {
        return array_map('strval', $this->config()->xpath('custom-fields/custom-field[@action="' . $action . '"]'));
    }

    public function test_mail_post_type_is_translatable(): void
    {
        $types = $this->config()->xpath('custom-types/custom-type[.="mail"]');

        $this->assertCount(1, $types);
        $this->assertSame('1', (string) $types[0]['translate']);
    }

    public function test_subject_and_content_are_translated(): void
    {
        $translated = $this->fields('translate');

        $this->assertContains('_lcmt_mail_subject', $translated);
        $this->assertContains('_lcmt_mail_content', $translated);
    }

    public function test_technical_fields_are_copied(): void
    {
        $copied = $this->fields('copy');

        foreach (['_lcmt_mail_key', '_lcmt_mail_to', '_lcmt_mail_reply_to', '_lcmt_mail_store_submissions', '_lcmt_mail_confirmation_page'] as $name) {
            $this->assertContains($name, $copied);
        }
    }

    public function test_success_message_is_an_admin_text(): void
    {
        $this->assertCount(1, $this->config()->xpath('admin-texts/key[@name="lcmt_mailer_success_message"]'));
    }

    public function test_meta_names_match_the_code(): void
    {
        $constants = (new \ReflectionClass(MetaFields::class))->getConstants();

        foreach (array_merge($this->fields('translate'), $this->fields('copy')) as $name) {
            $this->assertContains($name, $constants, $name . ' is not a MetaFields constant');
        }

        $this->assertCount(7, array_merge($this->fields('translate'), $this->fields('copy')));
    }
}

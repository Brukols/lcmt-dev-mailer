<?php

use LcmtDevMailer\AdminMailSender;
use LcmtDevMailer\FieldParser;
use PHPUnit\Framework\TestCase;

/**
 * The form file written by "Generate form template".
 */
class FormTemplateTest extends TestCase
{
    public function test_a_text_field_keeps_its_input(): void
    {
        $template = AdminMailSender::buildFormTemplate('contact', FieldParser::parse('[email* email]'));

        $this->assertStringContainsString('<input type="email" id="email" name="email" required />', $template);
    }

    public function test_a_file_field_gets_a_file_input_with_the_accept_helper(): void
    {
        $template = AdminMailSender::buildFormTemplate('devis', FieldParser::parse('[brief* file]'));

        $this->assertStringContainsString(
            '<input type="file" id="brief" name="brief" accept="<?php echo esc_attr(function_exists(\'lcmt_mailer_file_accept\') ? lcmt_mailer_file_accept(\'devis\') : \'\'); ?>" required />',
            $template
        );
        $this->assertStringContainsString("lcmt_mailer_file_hint('devis')", $template);
        $this->assertStringContainsString('data-lcmt-file-name="brief"', $template);
    }

    public function test_the_generated_template_is_valid_php(): void
    {
        $template = AdminMailSender::buildFormTemplate('devis', FieldParser::parse('[name*] [brief file] [message textarea]'));
        $file     = tempnam(sys_get_temp_dir(), 'lcmt');
        file_put_contents($file, $template);

        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        unlink($file);

        $this->assertSame(0, $code, implode("\n", $output));
    }
}

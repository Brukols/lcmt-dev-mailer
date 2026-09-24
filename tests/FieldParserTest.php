<?php

use LcmtDevMailer\FieldParser;
use PHPUnit\Framework\TestCase;

class FieldParserTest extends TestCase
{
    public function testReadsNameRequiredAndType(): void
    {
        $fields = FieldParser::parse('[firstname*] [email* email] [phone phone] [message textarea]');

        $this->assertSame(['name' => 'firstname', 'required' => true, 'type' => 'text'], $fields['firstname']);
        $this->assertSame(['name' => 'email', 'required' => true, 'type' => 'email'], $fields['email']);
        $this->assertSame(['name' => 'phone', 'required' => false, 'type' => 'tel'], $fields['phone']);
        $this->assertSame(['name' => 'message', 'required' => false, 'type' => 'textarea'], $fields['message']);
    }

    public function testMergesAFieldUsedInSeveralPlaces(): void
    {
        $fields = FieldParser::parse('[email]', 'Reply to [email* email]');

        $this->assertCount(1, $fields);
        $this->assertTrue($fields['email']['required']);
        $this->assertSame('email', $fields['email']['type']);
    }

    public function testFallsBackToTextForAnUnknownType(): void
    {
        $fields = FieldParser::parse('[age* years]');

        $this->assertSame('text', $fields['age']['type']);
    }

    public function testIgnoresTextThatIsNotAPlaceholder(): void
    {
        $this->assertSame([], FieldParser::parse('Price [10 €] and [ spaced ] and [-dash]'));
    }
}

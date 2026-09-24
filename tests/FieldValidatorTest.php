<?php

use LcmtDevMailer\FieldValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldValidatorTest extends TestCase
{
    public static function validPhones(): array
    {
        return [
            'international with spaces'     => ['+33 6 12 34 56 78'],
            'international without spaces'  => ['+33612345678'],
            'national with spaces'          => ['06 12 34 56 78'],
            'national without spaces'       => ['0612345678'],
            'dots'                          => ['06.12.34.56.78'],
            'dashes'                        => ['06-12-34-56-78'],
            'slashes'                       => ['06/12/34/56/78'],
            'trunk digit in brackets'       => ['+33 (0)6 12 34 56 78'],
            '00 prefix'                     => ['0033 6 12 34 56 78'],
            'landline'                      => ['01 23 45 67 89'],
            'non-breaking spaces'           => ["06\u{00A0}12\u{00A0}34\u{00A0}56\u{00A0}78"],
            'narrow non-breaking spaces'    => ["+33\u{202F}6\u{202F}12\u{202F}34\u{202F}56\u{202F}78"],
            'north american'                => ['+1 (555) 123-4567'],
            'belgian'                       => ['+32 470 12 34 56'],
            'swiss'                         => ['+41 79 123 45 67'],
            'longest E.164 with 00 and (0)' => ['00 49 (0) 123 456 789 012 3'],
        ];
    }

    public static function invalidPhones(): array
    {
        return [
            'separators only'   => ['----'],
            'brackets only'     => ['(())'],
            'too few digits'    => ['12 34'],
            'too many digits'   => ['0612345678061234567806'],
            'letters'           => ['abc'],
            'extension in text' => ['06 12 34 56 78 poste 12'],
            'plus not in front' => ['06 12 34 56 78+'],
            'two plus signs'    => ['++33 6 12 34 56 78'],
            'email'             => ['jean@example.com'],
        ];
    }

    #[DataProvider('validPhones')]
    public function testAcceptsPhone(string $value): void
    {
        $this->assertTrue(FieldValidator::isValid($value, 'tel'));
    }

    #[DataProvider('invalidPhones')]
    public function testRejectsPhone(string $value): void
    {
        $this->assertFalse(FieldValidator::isValid($value, 'tel'));
    }

    public function testChecksUrls(): void
    {
        $this->assertTrue(FieldValidator::isValid('https://example.com/page?a=1', 'url'));
        $this->assertTrue(FieldValidator::isValid('http://example.com', 'url'));
        $this->assertFalse(FieldValidator::isValid('javascript:alert(1)', 'url'));
        $this->assertFalse(FieldValidator::isValid('ftp://example.com', 'url'));
        $this->assertFalse(FieldValidator::isValid('example.com', 'url'));
    }

    public function testChecksNumbers(): void
    {
        $this->assertTrue(FieldValidator::isValid('42', 'number'));
        $this->assertTrue(FieldValidator::isValid('-3.5', 'number'));
        $this->assertFalse(FieldValidator::isValid('42 ans', 'number'));
    }

    public function testLeavesFreeTextTypesAlone(): void
    {
        foreach (['text', 'textarea', 'date', 'hidden', 'password'] as $type) {
            $this->assertTrue(FieldValidator::isValid('anything <at> all', $type), $type);
        }
    }

    public function testNamesTheFieldInTheError(): void
    {
        $this->assertNull(FieldValidator::error('06 12 34 56 78', 'tel', 'phone'));
        $this->assertSame(
            'The field "phone" must be a valid phone number.',
            FieldValidator::error('----', 'tel', 'phone')
        );
    }
}

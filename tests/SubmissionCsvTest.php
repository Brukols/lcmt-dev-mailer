<?php

use LcmtDevMailer\SubmissionCsv;
use PHPUnit\Framework\TestCase;

class SubmissionCsvTest extends TestCase
{
    private static function row(array $overrides): array
    {
        return array_merge(array_fill_keys(SubmissionCsv::COLUMNS, ''), ['fields' => []], $overrides);
    }

    public function testAddsOneColumnPerFieldAcrossForms(): void
    {
        $table = SubmissionCsv::table([
            self::row(['form_key' => 'contact', 'fields' => [['name' => 'email', 'type' => 'email', 'value' => 'a@b.fr']]]),
            self::row(['form_key' => 'quote', 'fields' => [['name' => 'budget', 'type' => 'number', 'value' => '1200']]]),
        ]);

        $header = $table[0];
        $this->assertSame(['email', 'budget'], array_slice($header, count(SubmissionCsv::COLUMNS)));
        $this->assertSame(['a@b.fr', ''], array_slice($table[1], count(SubmissionCsv::COLUMNS)));
        $this->assertSame(['', '1200'], array_slice($table[2], count(SubmissionCsv::COLUMNS)));
    }

    public function testExportsAnonymizedRowsWithEmptyFields(): void
    {
        $table = SubmissionCsv::table([self::row(['form_key' => 'contact', 'fields' => null])]);

        $this->assertCount(count(SubmissionCsv::COLUMNS), $table[1]);
    }

    public function testNeutralisesFormulas(): void
    {
        foreach (['=HYPERLINK("x")', '+33 6 12', '-2+3', '@SUM(A1)', "\tcmd"] as $value) {
            $this->assertSame("'" . $value, SubmissionCsv::cell($value), $value);
        }

        $this->assertSame('Bonjour = merci', SubmissionCsv::cell('Bonjour = merci'));
    }

    public function testNeutralisesFormulasInsideFields(): void
    {
        $table = SubmissionCsv::table([
            self::row(['fields' => [['name' => 'message', 'type' => 'textarea', 'value' => '=1+1']]]),
        ]);

        $this->assertSame("'=1+1", end($table[1]));
    }
}

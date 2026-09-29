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

    public function testExportsTheBrowserSystemAndRawUserAgent(): void
    {
        $this->assertSame(['browser', 'os', 'user_agent'], array_slice(SubmissionCsv::COLUMNS, -3));

        $table = SubmissionCsv::table([
            self::row(['browser' => 'Chrome', 'os' => 'Windows', 'user_agent' => '=Mozilla/5.0']),
            self::row(['fields' => null, 'user_agent' => null]),
        ]);

        $at = array_flip($table[0]);
        $this->assertSame('Chrome', $table[1][$at['browser']]);
        $this->assertSame('Windows', $table[1][$at['os']]);
        $this->assertSame("'=Mozilla/5.0", $table[1][$at['user_agent']], 'through cell()');
        $this->assertSame('', $table[2][$at['user_agent']], 'anonymized');
    }
}

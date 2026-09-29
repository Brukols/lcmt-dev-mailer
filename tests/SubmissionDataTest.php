<?php

use LcmtDevMailer\SubmissionData;
use PHPUnit\Framework\TestCase;

class SubmissionDataTest extends TestCase
{
    private const FIELDS = [
        'firstname' => ['name' => 'firstname', 'required' => true, 'type' => 'text'],
        'email'     => ['name' => 'email', 'required' => true, 'type' => 'email'],
        'secret'    => ['name' => 'secret', 'required' => false, 'type' => 'password'],
        'message'   => ['name' => 'message', 'required' => false, 'type' => 'textarea'],
    ];

    private const VALUES = [
        'firstname' => 'Jeanne',
        'email'     => 'Jeanne@Example.com',
        'secret'    => 'hunter2',
        'message'   => "Line 1\nLine 2",
    ];

    public function testSnapshotKeepsEveryFieldButPasswords(): void
    {
        $this->assertSame([
            ['name' => 'firstname', 'type' => 'text', 'value' => 'Jeanne'],
            ['name' => 'email', 'type' => 'email', 'value' => 'Jeanne@Example.com'],
            ['name' => 'message', 'type' => 'textarea', 'value' => "Line 1\nLine 2"],
        ], SubmissionData::snapshot(self::FIELDS, self::VALUES));
    }

    public function testSnapshotStoresMissingValuesAsEmpty(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, []);

        $this->assertSame('', $snapshot[0]['value']);
    }

    public function testPlaceholdersNeverCarryAPassword(): void
    {
        $placeholders = SubmissionData::toPlaceholders(SubmissionData::snapshot(self::FIELDS, self::VALUES));

        $this->assertSame('Jeanne', $placeholders['[firstname]']);
        $this->assertSame('Jeanne', $placeholders['[firstname*]']);
        $this->assertArrayNotHasKey('[secret]', $placeholders);
        $this->assertNotContains('hunter2', $placeholders);
    }

    public function testMatchesAnEmailWhateverItsCase(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, self::VALUES);

        $this->assertTrue(SubmissionData::containsEmail($snapshot, ' jeanne@example.COM '));
        $this->assertFalse(SubmissionData::containsEmail($snapshot, 'jeanne@example.org'));
        $this->assertFalse(SubmissionData::containsEmail($snapshot, ''));
    }

    public function testSummaryNamesTheSender(): void
    {
        $snapshot = SubmissionData::snapshot(self::FIELDS, self::VALUES);

        $this->assertSame('Jeanne · Jeanne@Example.com', SubmissionData::summary($snapshot));
        $this->assertSame('', SubmissionData::summary([]));
    }

    public function testSnapshotCapsEachValueAtTenThousandCharacters(): void
    {
        $long     = str_repeat('é', SubmissionData::MAX_VALUE_LENGTH + 50);
        $snapshot = SubmissionData::snapshot(self::FIELDS, ['message' => $long, 'firstname' => 'Jeanne']);

        $this->assertSame(10000, SubmissionData::MAX_VALUE_LENGTH);
        $this->assertSame(10000, mb_strlen($snapshot[2]['value'], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($snapshot[2]['value'], 'UTF-8'), 'no multibyte character cut in half');
        $this->assertSame('Jeanne', $snapshot[0]['value']);
    }

    public function testPersonalColumnsAreTheOnesAnonymizationClears(): void
    {
        $this->assertSame(['fields', 'mail_error', 'user_agent'], SubmissionData::PERSONAL_COLUMNS);
    }
}

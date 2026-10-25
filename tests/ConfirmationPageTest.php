<?php

use LcmtDevMailer\ConfirmationPage;
use PHPUnit\Framework\TestCase;

class ConfirmationPageTest extends TestCase
{
    /**
     * @param list<int> $published
     */
    private function publishedAmong(array $published): callable
    {
        return static fn (int $id): bool => in_array($id, $published, true);
    }

    public function test_stored_ids_are_read_as_positive_integers(): void
    {
        $this->assertSame(12, ConfirmationPage::normalizeId('12'));
        $this->assertSame(12, ConfirmationPage::normalizeId(12));
        $this->assertSame(12, ConfirmationPage::normalizeId(' 12 '));
    }

    public function test_empty_or_invalid_ids_mean_no_page(): void
    {
        foreach (['', '0', 0, null, false, '-3', -3, 'abc', '12abc', '1.5', [], '1e3'] as $raw) {
            $this->assertSame(0, ConfirmationPage::normalizeId($raw), var_export($raw, true));
        }
    }

    public function test_no_page_gives_no_target(): void
    {
        $this->assertSame(0, ConfirmationPage::pick(0, 0, $this->publishedAmong([])));
    }

    public function test_published_page_without_translation_is_the_target(): void
    {
        $this->assertSame(10, ConfirmationPage::pick(10, 0, $this->publishedAmong([10])));
    }

    public function test_published_translation_is_preferred(): void
    {
        $this->assertSame(20, ConfirmationPage::pick(10, 20, $this->publishedAmong([10, 20])));
    }

    public function test_unpublished_translation_falls_back_to_the_page(): void
    {
        $this->assertSame(10, ConfirmationPage::pick(10, 20, $this->publishedAmong([10])));
    }

    public function test_unpublished_page_gives_no_target(): void
    {
        $this->assertSame(0, ConfirmationPage::pick(10, 0, $this->publishedAmong([])));
        $this->assertSame(0, ConfirmationPage::pick(10, 20, $this->publishedAmong([])));
    }

    public function test_published_translation_of_an_unpublished_page_is_used(): void
    {
        $this->assertSame(20, ConfirmationPage::pick(10, 20, $this->publishedAmong([20])));
    }

    public function test_translation_equal_to_the_page_is_the_page(): void
    {
        $this->assertSame(10, ConfirmationPage::pick(10, 10, $this->publishedAmong([10])));
    }
}

<?php

use LcmtDevMailer\FormRenderer;
use PHPUnit\Framework\TestCase;

class FormRendererTest extends TestCase
{
    private const W = '<div data-lcmt-captcha="x"></div>';

    public function test_widget_goes_before_a_button_without_type(): void
    {
        $this->assertSame(
            '<input name="a">' . self::W . '<button class="btn">Send</button>',
            FormRenderer::placeWidget('<input name="a"><button class="btn">Send</button>', self::W)
        );
    }

    public function test_widget_goes_before_a_submit_button_whatever_the_quotes_and_case(): void
    {
        foreach (["<button type='submit'>", '<BUTTON TYPE="Submit">', '<button type=submit>', '<button class="a" type = "submit" disabled>'] as $button) {
            $this->assertSame(
                '<p>x</p>' . self::W . $button . 'Go</button>',
                FormRenderer::placeWidget('<p>x</p>' . $button . 'Go</button>', self::W),
                $button
            );
        }
    }

    public function test_widget_goes_before_an_input_submit(): void
    {
        $this->assertSame(
            '<textarea></textarea>' . self::W . '<input type="submit" value="Send">',
            FormRenderer::placeWidget('<textarea></textarea><input type="submit" value="Send">', self::W)
        );
    }

    public function test_other_buttons_are_skipped(): void
    {
        $html = '<button type="button">Help</button><button data-type="x" type="reset">R</button><input type="text"><button>Send</button>';

        $this->assertSame(
            '<button type="button">Help</button><button data-type="x" type="reset">R</button><input type="text">' . self::W . '<button>Send</button>',
            FormRenderer::placeWidget($html, self::W)
        );
    }

    public function test_the_slot_wins_over_a_button_and_extra_slots_are_removed(): void
    {
        $html = '<input>' . FormRenderer::CAPTCHA_SLOT . '<button>Send</button>' . FormRenderer::CAPTCHA_SLOT;

        $this->assertSame('<input>' . self::W . '<button>Send</button>', FormRenderer::placeWidget($html, self::W));
    }

    public function test_without_slot_nor_submit_control_the_widget_is_appended(): void
    {
        $this->assertSame(
            '<input name="a"><button type="button">x</button>' . self::W,
            FormRenderer::placeWidget('<input name="a"><button type="button">x</button>', self::W)
        );
    }

    public function test_an_empty_widget_removes_the_slot(): void
    {
        $this->assertSame('<input><button>Send</button>', FormRenderer::placeWidget('<input>' . FormRenderer::CAPTCHA_SLOT . '<button>Send</button>', ''));
        $this->assertSame('<input><button>Send</button>', FormRenderer::placeWidget('<input><button>Send</button>', ''));
    }

    public function test_a_commented_out_button_is_skipped(): void
    {
        $this->assertSame(
            '<!-- <button>old</button> --><input>' . self::W . '<button type="submit">Send</button>',
            FormRenderer::placeWidget('<!-- <button>old</button> --><input><button type="submit">Send</button>', self::W)
        );
    }

    public function test_buttons_inside_raw_text_elements_are_skipped(): void
    {
        foreach (['<textarea>use <button> here</textarea>', '<script>var b = "<button>";</script>', '<style>/* <button> */</style>', '<template><button>x</button></template>'] as $raw) {
            $this->assertSame($raw . self::W . '<button>Send</button>', FormRenderer::placeWidget($raw . '<button>Send</button>', self::W), $raw);
        }
    }

    public function test_a_button_inside_an_attribute_value_is_skipped(): void
    {
        $this->assertSame(
            '<div data-tpl="<button>"></div>' . self::W . '<button>Send</button>',
            FormRenderer::placeWidget('<div data-tpl="<button>"></div><button>Send</button>', self::W)
        );
    }

    public function test_type_submit_inside_an_attribute_value_does_not_count(): void
    {
        // Not a submit control: its type is text
        $this->assertSame(
            '<input value="type=submit" type="text">' . self::W,
            FormRenderer::placeWidget('<input value="type=submit" type="text">', self::W)
        );

        // No type of its own: a submit button
        $this->assertSame(
            '<p>x</p>' . self::W . '<button data-x="type=a">Send</button>',
            FormRenderer::placeWidget('<p>x</p><button data-x="type=a">Send</button>', self::W)
        );
    }
}

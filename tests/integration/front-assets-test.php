<?php

use LcmtDevMailer\FormRenderer;

/**
 * Starts from scratch: the front script registered by the plugin, nothing
 * enqueued yet.
 */
function lcmt_it_front_assets(): void
{
    wp_dequeue_script('lcmt-form-handler');
    wp_deregister_script('lcmt-form-handler');
    wp_deregister_style('lcmt-form-handler');
    FormRenderer::registerAssets();
}

function lcmt_it_front_data(): string
{
    return (string) wp_scripts()->get_data('lcmt-form-handler', 'data');
}

lcmt_it('the front strings come with the script as soon as it is registered', function () {
    lcmt_it_front_assets();

    lcmt_assert_true(str_contains(lcmt_it_front_data(), 'var lcmtMailerFront'), 'lcmtMailerFront');
    lcmt_assert_true(str_contains(lcmt_it_front_data(), 'Please fill in all required fields.'), 'requiredFields');
});

lcmt_it('a theme enqueuing the script early still gets the front strings', function () {
    lcmt_it_front_assets();

    // What a theme does to get the stylesheet into the head.
    wp_enqueue_script('lcmt-form-handler');

    lcmt_assert_true(str_contains(lcmt_it_front_data(), 'var lcmtMailerFront'), 'lcmtMailerFront');
    lcmt_assert_same(1, substr_count(lcmt_it_front_data(), 'var lcmtMailerFront'), 'printed once');
});

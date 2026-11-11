<?php

/**
 * Built-in placeholders filled by the server cannot be set by a visitor
 * (security review F1): the signed-in account's email and profile link come
 * from wp_get_current_user(), whatever the request body says.
 */

/**
 * Post a JSON body to a template using the current-user placeholders, and
 * return the body of the mail that would have been sent.
 */
function lcmt_sf_send(array $body): string
{
    [, $key] = lcmt_it_template([
        '_lcmt_mail_subject' => 'Request',
        '_lcmt_mail_content' => 'From [currentUserEmail] (profile: [currentUserLink]) — [message* textarea]',
        '_lcmt_mail_captcha' => 'none',
    ]);

    $sent = '';
    remove_all_filters('pre_wp_mail');
    add_filter('pre_wp_mail', static function ($return, array $atts) use (&$sent) {
        $sent = (string) $atts['message'];
        return true;
    }, 10, 2);

    $request = new WP_REST_Request('POST', '/lcmt-mailer/v1/forms/' . $key);
    $request->set_header('content-type', 'application/json');
    $request->set_body(wp_json_encode($body));
    $response = rest_do_request($request);

    lcmt_assert_same(200, $response->get_status(), 'the form is sent');

    return $sent;
}

lcmt_it('a visitor cannot set the signed-in account placeholders', function () {
    wp_set_current_user(0);

    $mail = lcmt_sf_send([
        'message'          => 'Hello',
        'currentUserEmail' => 'ceo@victim.example',
        'currentUserLink'  => 'https://attacker.example/wp-login.php',
    ]);

    lcmt_assert_true(strpos($mail, 'Hello') !== false, 'the message is in the mail');
    lcmt_assert_true(strpos($mail, 'ceo@victim.example') === false, 'the posted email is ignored');
    lcmt_assert_true(strpos($mail, 'attacker.example') === false, 'the posted link is ignored');
});

lcmt_it('a signed-in visitor gets their own account in the mail, not the posted one', function () {
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    lcmt_assert_true($admins !== [], 'an administrator exists');
    wp_set_current_user($admins[0]->ID);

    try {
        $mail = lcmt_sf_send(['message' => 'Hello', 'currentUserEmail' => 'ceo@victim.example']);
    } finally {
        wp_set_current_user(0);
    }

    lcmt_assert_true(strpos($mail, $admins[0]->user_email) !== false, 'the real account email is in the mail');
    lcmt_assert_true(strpos($mail, 'ceo@victim.example') === false, 'the posted email is ignored');
});

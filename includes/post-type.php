<?php

namespace LcmtDevMailer;

if (!defined('ABSPATH')) {
    exit;
}

class PostType
{
    public const SLUG = 'mail';

    public static function register(): void
    {
        if (post_type_exists(self::SLUG)) {
            return;
        }

        register_post_type(self::SLUG, [
            'labels' => [
                'name'               => __('Mails', 'lcmt-dev-mailer'),
                'singular_name'      => __('Mail', 'lcmt-dev-mailer'),
                'add_new'            => __('Add New', 'lcmt-dev-mailer'),
                'add_new_item'       => __('Add New Mail', 'lcmt-dev-mailer'),
                'edit_item'          => __('Edit Mail', 'lcmt-dev-mailer'),
                'new_item'           => __('New Mail', 'lcmt-dev-mailer'),
                'view_item'          => __('View Mail', 'lcmt-dev-mailer'),
                'search_items'       => __('Search Mails', 'lcmt-dev-mailer'),
                'not_found'          => __('No mails found', 'lcmt-dev-mailer'),
                'not_found_in_trash' => __('No mails found in trash', 'lcmt-dev-mailer'),
            ],
            'public'              => true,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_icon'           => 'dashicons-email-alt2',
            'supports'            => ['title', 'page-attributes'],
            'rewrite'             => false,
        ]);
    }
}

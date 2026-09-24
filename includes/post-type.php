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
                'name'               => __('Email templates', 'lcmt-dev-mailer'),
                'singular_name'      => __('Email template', 'lcmt-dev-mailer'),
                'add_new'            => __('Add New', 'lcmt-dev-mailer'),
                'add_new_item'       => __('Add New Email Template', 'lcmt-dev-mailer'),
                'edit_item'          => __('Edit Email Template', 'lcmt-dev-mailer'),
                'new_item'           => __('New Email Template', 'lcmt-dev-mailer'),
                'view_item'          => __('View Email Template', 'lcmt-dev-mailer'),
                'search_items'       => __('Search Email Templates', 'lcmt-dev-mailer'),
                'not_found'          => __('No email templates found', 'lcmt-dev-mailer'),
                'not_found_in_trash' => __('No email templates found in trash', 'lcmt-dev-mailer'),
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
            'capabilities'        => self::adminOnlyCapabilities(),
            'map_meta_cap'        => false,
        ]);
    }

    /**
     * Map every post type capability to manage_options.
     *
     * A mail decides where form submissions go and sends from the site's
     * domain, so only administrators may create, edit or delete one.
     *
     * @return array<string, string>
     */
    private static function adminOnlyCapabilities(): array
    {
        $capabilities = [
            'edit_post', 'read_post', 'delete_post',
            'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts',
            'publish_posts', 'read_private_posts', 'create_posts',
            'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts',
        ];

        return array_fill_keys($capabilities, 'manage_options');
    }
}

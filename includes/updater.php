<?php

namespace LcmtDevMailer;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Offers new versions in Dashboard → Updates, like a plugin from wordpress.org.
 *
 * Versions come from the GitHub releases of the plugin repository: each
 * release carries a lcmt-dev-mailer.zip built by the release workflow.
 */
class Updater
{
    public const REPOSITORY = 'https://github.com/Brukols/lcmt-dev-mailer/';
    public const SLUG = 'lcmt-dev-mailer';

    public static function register(string $pluginFile): void
    {
        require_once LCMT_MAILER_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';

        $checker = PucFactory::buildUpdateChecker(self::REPOSITORY, $pluginFile, self::SLUG);

        // Install the built zip attached to the release, not GitHub's
        // source archive, which would unpack into a differently named folder.
        $checker->getVcsApi()->enableReleaseAssets('/^' . preg_quote(self::SLUG, '/') . '\.zip$/');
    }
}

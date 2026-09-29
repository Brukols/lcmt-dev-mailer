<?php

/**
 * Runs the integration tests against the local WordPress database.
 *
 * 1. Sync the plugin to the site (the copy is not a git checkout):
 *    rsync -a --delete --exclude .git --exclude node_modules --exclude docs --exclude tests --exclude .phpunit.cache \
 *      /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer/ /Volumes/Samsung_T5/Freelance/live-decor-production/wp-content/plugins/lcmt-dev-mailer/
 * 2. Run:
 *    cd /Volumes/Samsung_T5/Freelance/live-decor-production && \
 *      wp eval-file /Volumes/Samsung_T5/Freelance/lcmt-dev-mailer/tests/integration/run.php
 *
 * Each test runs in a transaction that is rolled back, so no data is left
 * behind. DDL must never run inside a test: it commits implicitly.
 */

require_once __DIR__ . '/harness.php';

// The tests read the English source strings, whatever language the site uses.
switch_to_locale('en_US');

$files = glob(__DIR__ . '/*-test.php') ?: [];
sort($files);

foreach ($files as $file) {
    require $file;
}

lcmt_it_report();

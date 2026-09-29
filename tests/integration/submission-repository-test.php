<?php

use LcmtDevMailer\SubmissionRepository;

// Stats tests use far-future dates so rows already on the site never count.
const LCMT_IT_FUTURE = '2090-01-01 00:00:00';

lcmt_it('insert and find round trip decodes fields', function () {
    $fields = ['name' => 'Élodie', 'site' => 'https://example.com/a/b'];
    $id     = lcmt_it_insert(['fields' => wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $row    = SubmissionRepository::find($id);

    lcmt_assert_same($fields, $row['fields']);
    lcmt_assert_same($id, (int) $row['id']);
});

lcmt_it('stored fields keep accents and slashes unescaped', function () {
    $id  = lcmt_it_insert(['fields' => wp_json_encode(['a' => 'é/x'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $raw = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare('SELECT fields FROM ' . SubmissionRepository::table() . ' WHERE id = %d', $id));

    lcmt_assert_same('{"a":"é/x"}', $raw);
});

lcmt_it('null fields come back as null', function () {
    $id = lcmt_it_insert(['fields' => null]);

    lcmt_assert_null(SubmissionRepository::find($id)['fields']);
});

lcmt_it('find returns null for an unknown id', function () {
    lcmt_assert_null(SubmissionRepository::find(999999999));
});

lcmt_it('update changes columns', function () {
    $id = lcmt_it_insert();

    lcmt_assert_true(SubmissionRepository::update($id, ['status' => 'read', 'mail_sent' => 0]));

    $row = SubmissionRepository::find($id);
    lcmt_assert_same('read', $row['status']);
    lcmt_assert_same('0', (string) $row['mail_sent']);
});

lcmt_it('search hides spam by default and shows it on request', function () {
    $key  = lcmt_it_key();
    $new  = lcmt_it_insert(['form_key' => $key]);
    $spam = lcmt_it_insert(['form_key' => $key, 'status' => 'spam']);

    lcmt_assert_same([$new], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key])));
    lcmt_assert_same(1, SubmissionRepository::count(['form_key' => $key]));
    lcmt_assert_same([$spam], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'status' => 'spam'])));
});

lcmt_it('search filters by status', function () {
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key]);
    $read = lcmt_it_insert(['form_key' => $key, 'status' => 'read']);

    lcmt_assert_same([$read], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'status' => 'read'])));
    lcmt_assert_same(2, SubmissionRepository::count(['form_key' => $key, 'status' => '']));
});

lcmt_it('search filters by failed, channel and field text', function () {
    $key    = lcmt_it_key();
    $failed = lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0, 'channel' => 'email', 'fields' => wp_json_encode(['msg' => 'needle 100%_x'])]);
    lcmt_it_insert(['form_key' => $key]);

    lcmt_assert_same([$failed], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'failed' => true])));
    lcmt_assert_same([$failed], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'channel' => 'email'])));
    lcmt_assert_same([$failed], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'search' => 'needle'])));
    lcmt_assert_same([$failed], lcmt_it_ids(SubmissionRepository::search(['form_key' => $key, 'search' => '100%_x'])));
    lcmt_assert_same(0, SubmissionRepository::count(['form_key' => $key, 'search' => '100%_y']));
});

lcmt_it('search orders newest first and paginates', function () {
    $key = lcmt_it_key();
    $a   = lcmt_it_insert(['form_key' => $key, 'created_at' => '2001-01-01 10:00:00']);
    $b   = lcmt_it_insert(['form_key' => $key, 'created_at' => '2001-01-03 10:00:00']);
    $c   = lcmt_it_insert(['form_key' => $key, 'created_at' => '2001-01-02 10:00:00']);
    $f   = ['form_key' => $key];

    lcmt_assert_same([$b, $c, $a], lcmt_it_ids(SubmissionRepository::search($f)), 'perPage 0 = all');
    lcmt_assert_same([$b, $c], lcmt_it_ids(SubmissionRepository::search($f, 2, 1)));
    lcmt_assert_same([$a], lcmt_it_ids(SubmissionRepository::search($f, 2, 2)));
    lcmt_assert_same(3, SubmissionRepository::count($f));
});

lcmt_it('setStatus updates the given rows and ignores unknown statuses', function () {
    $one = lcmt_it_insert();
    $two = lcmt_it_insert();

    SubmissionRepository::setStatus([$one], 'processed');
    lcmt_assert_same('processed', SubmissionRepository::find($one)['status']);
    lcmt_assert_same('new', SubmissionRepository::find($two)['status']);

    SubmissionRepository::setStatus([$one], 'bogus');
    SubmissionRepository::setStatus([], 'read');
    lcmt_assert_same('processed', SubmissionRepository::find($one)['status']);
});

lcmt_it('delete removes only the given rows', function () {
    $one = lcmt_it_insert();
    $two = lcmt_it_insert();

    SubmissionRepository::delete([$one]);
    SubmissionRepository::delete([]);

    lcmt_assert_null(SubmissionRepository::find($one));
    lcmt_assert_true(SubmissionRepository::find($two) !== null);
});

lcmt_it('anonymize clears personal columns and keeps the context', function () {
    $id = lcmt_it_insert([
        'mail_error' => 'boom', 'page_path' => '/contact', 'channel' => 'google_ads',
        'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'spring',
        'created_at' => '2001-02-03 04:05:06', 'form_seconds' => 42,
    ]);
    $other = lcmt_it_insert(['created_at' => '2001-02-03 04:05:06', 'form_seconds' => 42]);

    SubmissionRepository::anonymize([$id]);
    SubmissionRepository::anonymize([]);

    $row = SubmissionRepository::find($id);
    lcmt_assert_null($row['fields']);
    lcmt_assert_null($row['mail_error']);
    lcmt_assert_true($row['anonymized_at'] !== null, 'anonymized_at set');
    lcmt_assert_same('/contact', $row['page_path']);
    lcmt_assert_same('google_ads', $row['channel']);
    lcmt_assert_same('google', $row['utm_source']);
    lcmt_assert_same('cpc', $row['utm_medium']);
    lcmt_assert_same('spring', $row['utm_campaign']);
    lcmt_assert_same('2001-02-03 00:00:00', $row['created_at'], 'only the day is kept');
    lcmt_assert_null($row['form_seconds'], 'time on the form dropped');
    lcmt_assert_null(SubmissionRepository::find($other)['anonymized_at']);
    lcmt_assert_same('2001-02-03 04:05:06', SubmissionRepository::find($other)['created_at'], 'other row untouched');
    lcmt_assert_same('42', (string) SubmissionRepository::find($other)['form_seconds'], 'other row keeps its time');
});

lcmt_it('anonymizeBefore only touches older, not yet anonymized rows', function () {
    $old      = lcmt_it_insert(['created_at' => '2001-01-01 10:20:30', 'form_seconds' => 42]);
    $done     = lcmt_it_insert(['created_at' => '2001-01-02 00:00:00', 'fields' => null, 'anonymized_at' => '2001-06-01 00:00:00']);
    $recent   = lcmt_it_insert(['created_at' => '2001-09-01 00:00:00']);
    $cutoff   = '2001-06-01 00:00:00';

    lcmt_assert_same(1, SubmissionRepository::anonymizeBefore($cutoff, 100));

    lcmt_assert_null(SubmissionRepository::find($old)['fields']);
    lcmt_assert_true(SubmissionRepository::find($old)['anonymized_at'] !== null);
    lcmt_assert_same('2001-01-01 00:00:00', SubmissionRepository::find($old)['created_at'], 'only the day is kept');
    lcmt_assert_null(SubmissionRepository::find($old)['form_seconds'], 'time on the form dropped');
    lcmt_assert_same('2001-06-01 00:00:00', SubmissionRepository::find($done)['anonymized_at'], 'already anonymized untouched');
    lcmt_assert_true(SubmissionRepository::find($recent)['fields'] !== null, 'recent row kept');
});

lcmt_it('anonymizeBefore respects the limit', function () {
    lcmt_it_insert(['created_at' => '2001-01-01 00:00:00']);
    lcmt_it_insert(['created_at' => '2001-01-02 00:00:00']);
    lcmt_it_insert(['created_at' => '2001-01-03 00:00:00']);

    lcmt_assert_same(2, SubmissionRepository::anonymizeBefore('2001-06-01 00:00:00', 2));
    lcmt_assert_same(1, SubmissionRepository::anonymizeBefore('2001-06-01 00:00:00', 2));
});

lcmt_it('deleteBefore removes only rows older than the cutoff', function () {
    $old    = lcmt_it_insert(['created_at' => '2001-01-01 00:00:00']);
    $recent = lcmt_it_insert(['created_at' => '2001-09-01 00:00:00']);

    lcmt_assert_same(1, SubmissionRepository::deleteBefore('2001-06-01 00:00:00', 100));

    lcmt_assert_null(SubmissionRepository::find($old));
    lcmt_assert_true(SubmissionRepository::find($recent) !== null);
});

lcmt_it('deleteAnonymizedBefore only deletes anonymized rows', function () {
    $plain = lcmt_it_insert(['created_at' => '2001-01-01 00:00:00']);
    $anon  = lcmt_it_insert(['created_at' => '2001-01-01 00:00:00', 'fields' => null, 'anonymized_at' => '2001-02-01 00:00:00']);
    $newer = lcmt_it_insert(['created_at' => '2001-09-01 00:00:00', 'fields' => null, 'anonymized_at' => '2001-09-02 00:00:00']);

    lcmt_assert_same(1, SubmissionRepository::deleteAnonymizedBefore('2001-06-01 00:00:00', 100));

    lcmt_assert_true(SubmissionRepository::find($plain) !== null);
    lcmt_assert_null(SubmissionRepository::find($anon));
    lcmt_assert_true(SubmissionRepository::find($newer) !== null);
});

lcmt_it('findContaining ignores anonymized rows', function () {
    $needle = 'zq' . uniqid();
    $live   = lcmt_it_insert(['fields' => wp_json_encode(['email' => "{$needle}@example.com"])]);
    $anon   = lcmt_it_insert(['fields' => wp_json_encode(['email' => "{$needle}@example.com"])]);
    SubmissionRepository::anonymize([$anon]);

    lcmt_assert_same([$live], lcmt_it_ids(SubmissionRepository::findContaining($needle)));
});

lcmt_it('countUnread counts new rows only', function () {
    $before = SubmissionRepository::countUnread();

    lcmt_it_insert();
    lcmt_it_insert(['status' => 'read']);
    lcmt_it_insert(['status' => 'spam']);

    lcmt_assert_same($before + 1, SubmissionRepository::countUnread());
});

lcmt_it('unresolved failures exclude sent, handled, spam, anonymized and old rows', function () {
    $key   = lcmt_it_key();
    $since = '2001-01-01 00:00:00';
    $base  = ['form_key' => $key, 'mail_sent' => 0, 'mail_error' => 'boom'];
    $countBefore = SubmissionRepository::countUnresolvedFailures($since);

    $older  = lcmt_it_insert($base + ['created_at' => '2001-03-01 00:00:00']);
    $newer  = lcmt_it_insert($base + ['created_at' => '2001-04-01 00:00:00', 'status' => 'read']);
    lcmt_it_insert(['form_key' => $key, 'created_at' => '2001-03-01 00:00:00']); // sent
    lcmt_it_insert($base + ['created_at' => '2001-03-01 00:00:00', 'status' => 'processed']);
    lcmt_it_insert($base + ['created_at' => '2001-03-01 00:00:00', 'status' => 'spam']);
    lcmt_it_insert($base + ['created_at' => '2001-03-01 00:00:00', 'anonymized_at' => '2001-05-01 00:00:00']);
    lcmt_it_insert($base + ['created_at' => '2000-12-31 00:00:00']); // before since

    $mine = array_values(array_filter(
        SubmissionRepository::unresolvedFailures($since, 1000),
        static fn(array $row) => $row['form_key'] === $key
    ));

    lcmt_assert_same([$newer, $older], lcmt_it_ids($mine));
    lcmt_assert_same('boom', $mine[0]['mail_error']);
    lcmt_assert_same($countBefore + 2, SubmissionRepository::countUnresolvedFailures($since));
});

lcmt_it('unresolvedFailures respects the limit', function () {
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0, 'created_at' => '2001-01-02 00:00:00']);
    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0, 'created_at' => '2001-01-03 00:00:00']);

    $mine = static fn(array $rows) => array_filter($rows, static fn(array $row) => $row['form_key'] === $key);

    lcmt_assert_count(1, SubmissionRepository::unresolvedFailures('2001-01-01 00:00:00', 1));
    lcmt_assert_count(2, $mine(SubmissionRepository::unresolvedFailures('2001-01-01 00:00:00', 1000)));
});

lcmt_it('unresolved failures leave out emails still being sent (under 2 minutes old)', function () {
    $key   = lcmt_it_key();
    $since = gmdate('Y-m-d H:i:s', time() - 3600);
    $countBefore = SubmissionRepository::countUnresolvedFailures($since);

    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0, 'created_at' => gmdate('Y-m-d H:i:s', time() - 30)]);

    $mine = array_filter(
        SubmissionRepository::unresolvedFailures($since, 1000),
        static fn(array $row) => $row['form_key'] === $key
    );

    lcmt_assert_count(0, $mine, 'listed');
    lcmt_assert_same($countBefore, SubmissionRepository::countUnresolvedFailures($since), 'counted');

    lcmt_it_insert(['form_key' => $key, 'mail_sent' => 0, 'created_at' => gmdate('Y-m-d H:i:s', time() - 180)]);

    lcmt_assert_same($countBefore + 1, SubmissionRepository::countUnresolvedFailures($since), 'an older failure counts');
});

lcmt_it('formKeys lists distinct keys sorted', function () {
    $key = lcmt_it_key();
    lcmt_it_insert(['form_key' => $key]);
    lcmt_it_insert(['form_key' => $key]);

    $keys = SubmissionRepository::formKeys();

    lcmt_assert_same(1, count(array_keys($keys, $key, true)), 'listed once');
    $sorted = $keys;
    sort($sorted, SORT_STRING);
    lcmt_assert_same($sorted, $keys, 'sorted');
});

lcmt_it('countBy rejects a column that is not whitelisted', function () {
    lcmt_assert_same([], SubmissionRepository::countBy('fields', '2001-01-01 00:00:00'));
    lcmt_assert_same([], SubmissionRepository::countBy('id; DROP TABLE x', '2001-01-01 00:00:00'));
});

lcmt_it('countBy groups, orders by total, limits and skips spam', function () {
    $when = '2090-01-05 00:00:00';
    lcmt_it_insert(['channel' => 'email', 'created_at' => $when]);
    lcmt_it_insert(['channel' => 'direct', 'created_at' => $when]);
    lcmt_it_insert(['channel' => 'direct', 'created_at' => $when]);
    lcmt_it_insert(['channel' => 'social', 'created_at' => $when, 'status' => 'spam']);

    lcmt_assert_same(
        [['label' => 'direct', 'total' => 2], ['label' => 'email', 'total' => 1]],
        SubmissionRepository::countBy('channel', LCMT_IT_FUTURE)
    );
    lcmt_assert_same([['label' => 'direct', 'total' => 2]], SubmissionRepository::countBy('channel', LCMT_IT_FUTURE, 1));
});

lcmt_it('countByMonth groups by YYYY-MM oldest first', function () {
    lcmt_it_insert(['created_at' => '2090-03-10 00:00:00']);
    lcmt_it_insert(['created_at' => '2090-01-10 00:00:00']);
    lcmt_it_insert(['created_at' => '2090-01-20 00:00:00']);
    lcmt_it_insert(['created_at' => '2090-02-01 00:00:00', 'status' => 'spam']);

    lcmt_assert_same(
        [['label' => '2090-01', 'total' => 2], ['label' => '2090-03', 'total' => 1]],
        SubmissionRepository::countByMonth(LCMT_IT_FUTURE)
    );
});

lcmt_it('totals count sent and failed, skip spam and average the fill time', function () {
    lcmt_it_insert(['created_at' => '2090-01-05 00:00:00', 'form_seconds' => 10]);
    lcmt_it_insert(['created_at' => '2090-01-05 00:00:00', 'form_seconds' => 21, 'mail_sent' => 0]);
    lcmt_it_insert(['created_at' => '2090-01-05 00:00:00', 'form_seconds' => 999, 'status' => 'spam']);

    lcmt_assert_same(['total' => 2, 'failed' => 1, 'avg_seconds' => 16], SubmissionRepository::totals(LCMT_IT_FUTURE));
});

lcmt_it('totals give a null average without fill times and zeros without rows', function () {
    lcmt_assert_same(['total' => 0, 'failed' => 0, 'avg_seconds' => null], SubmissionRepository::totals(LCMT_IT_FUTURE));

    lcmt_it_insert(['created_at' => '2090-01-05 00:00:00']);

    lcmt_assert_same(['total' => 1, 'failed' => 0, 'avg_seconds' => null], SubmissionRepository::totals(LCMT_IT_FUTURE));
});

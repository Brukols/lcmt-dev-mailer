import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildSync } from 'esbuild';
import { setEnv } from './env.js';

var KEY = 'lcmtMailerLanding';

// The entry runs on load, so each scenario evaluates a fresh bundle of it.
var code = buildSync({
  entryPoints: [process.cwd() + '/assets/src/attribution.js'],
  bundle: true,
  format: 'iife',
  write: false,
}).outputFiles[0].text;

function run(options, setup) {
  var env = setEnv(options);
  if (setup) setup();
  (0, eval)(code);
  return env;
}

test('entry keeps the stored landing on an internal navigation', function () {
  var stored = JSON.stringify({ path: '/', referrer: '', utm_source: 'google' });
  var env = run({ url: 'https://site.test/about/', referrer: 'https://site.test/', storage: { [KEY]: stored } }, function () {
    window.lcmtMailerAttribution = { storeWithoutConsent: true };
  });
  assert.equal(env.storage.data[KEY], stored);
});

test('entry overwrites the stored landing on a new campaign', function () {
  var env = run({ url: 'https://site.test/promo/?utm_source=bing', storage: { [KEY]: JSON.stringify({ path: '/', referrer: '' }) } }, function () {
    window.lcmtMailerAttribution = { storeWithoutConsent: true };
  });
  var saved = JSON.parse(env.storage.data[KEY]);
  assert.equal(saved.path, '/promo/');
  assert.equal(saved.utm_source, 'bing');
});

test('entry writes nothing without consent until statistics are allowed', function () {
  var env = run({ url: 'https://site.test/?utm_source=google' }, function () {
    window.wp_has_consent = function () {
      return false;
    };
  });
  assert.equal(env.storage.data[KEY], undefined);

  env.document.dispatch('wp_listen_for_consent_change', { marketing: 'allow' });
  assert.equal(env.storage.data[KEY], undefined);

  env.document.dispatch('wp_listen_for_consent_change', { statistics: 'allow' });
  assert.equal(JSON.parse(env.storage.data[KEY]).utm_source, 'google');
});

test('entry ignores the site setting while an active consent API has not loaded', function () {
  var env = run({ url: 'https://site.test/?utm_source=google' }, function () {
    window.lcmtMailerAttribution = { storeWithoutConsent: true, consentApi: true };
  });
  assert.equal(env.storage.data[KEY], undefined);

  env.document.dispatch('wp_listen_for_consent_change', { statistics: 'deny' });
  assert.equal(env.storage.data[KEY], undefined);

  env.document.dispatch('wp_listen_for_consent_change', { statistics: 'allow' });
  assert.equal(JSON.parse(env.storage.data[KEY]).utm_source, 'google');
});

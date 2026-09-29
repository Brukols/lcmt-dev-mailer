import { test } from 'node:test';
import assert from 'node:assert/strict';
import { setEnv } from './env.js';
import {
  currentTouch,
  startsVisit,
  storedTouch,
  storeTouch,
  mayStore,
  device,
  formContext,
} from '../../assets/src/lib/attribution.js';

var KEY = 'lcmtMailerLanding';

test('currentTouch reads the path, referrer and UTM keys', function () {
  setEnv({ url: 'https://site.test/a/?utm_source=google&utm_medium=cpc&utm_campaign=spring', referrer: 'https://google.com/' });
  assert.deepEqual(currentTouch(), {
    path: '/a/',
    referrer: 'https://google.com/',
    utm_source: 'google',
    utm_medium: 'cpc',
    utm_campaign: 'spring',
  });
});

test('currentTouch cuts UTM values to 150 characters', function () {
  setEnv({ url: 'https://site.test/?utm_campaign=' + 'x'.repeat(300) });
  assert.equal(currentTouch().utm_campaign.length, 150);
});

test('currentTouch keeps the first matching click id by name only', function () {
  setEnv({ url: 'https://site.test/?fbclid=SECRETFB&gclid=SECRETG' });
  var touch = currentTouch();
  assert.equal(touch.click_id, 'gclid');
  assert.ok(!JSON.stringify(touch).includes('SECRET'));
});

test('startsVisit is true for utm_source, click id and external referrer', function () {
  setEnv();
  assert.equal(startsVisit({ path: '/', referrer: '', utm_source: 'x' }), true);
  assert.equal(startsVisit({ path: '/', referrer: '', click_id: 'gclid' }), true);
  assert.equal(startsVisit({ path: '/', referrer: 'https://other.test/x' }), true);
});

test('startsVisit is false for internal, missing and malformed referrers', function () {
  setEnv();
  assert.equal(startsVisit({ path: '/', referrer: 'https://site.test/other' }), false);
  assert.equal(startsVisit({ path: '/', referrer: '' }), false);
  assert.equal(startsVisit({ path: '/', referrer: 'not a url' }), false);
});

test('storedTouch is null when empty, garbage or storage throws', function () {
  setEnv();
  assert.equal(storedTouch(), null);
  setEnv({ storage: { [KEY]: '{oops' } });
  assert.equal(storedTouch(), null);
  setEnv({ storageThrows: true });
  assert.equal(storedTouch(), null);
});

test('storedTouch returns what storeTouch wrote', function () {
  setEnv();
  storeTouch({ path: '/x', referrer: '' });
  assert.deepEqual(storedTouch(), { path: '/x', referrer: '' });
});

test('storeTouch swallows a throwing setItem', function () {
  setEnv({ setItemThrows: true });
  assert.doesNotThrow(function () {
    storeTouch({ path: '/x' });
  });
});

test('mayStore follows wp_has_consent(statistics) when defined', function () {
  setEnv();
  window.lcmtMailerAttribution = { storeWithoutConsent: true };
  var asked = [];
  window.wp_has_consent = function (category) {
    asked.push(category);
    return false;
  };
  assert.equal(mayStore(), false);
  window.wp_has_consent = function () {
    return true;
  };
  window.lcmtMailerAttribution = { storeWithoutConsent: false };
  assert.equal(mayStore(), true);
  assert.deepEqual(asked, ['statistics']);
});

test('mayStore falls back to the site setting without a consent API', function () {
  setEnv();
  window.lcmtMailerAttribution = { storeWithoutConsent: true };
  assert.equal(mayStore(), true);
  window.lcmtMailerAttribution = { storeWithoutConsent: false };
  assert.equal(mayStore(), false);
  delete window.lcmtMailerAttribution;
  assert.equal(mayStore(), false);
});

test('device thresholds', function () {
  setEnv({ width: 767 });
  assert.equal(device(), 'mobile');
  setEnv({ width: 800, coarse: true });
  assert.equal(device(), 'tablet');
  setEnv({ width: 800, coarse: false });
  assert.equal(device(), 'desktop');
  setEnv({ width: 1300, coarse: true });
  assert.equal(device(), 'desktop');
});

test('formContext uses the stored landing when the page does not start a visit', function () {
  setEnv({ url: 'https://site.test/contact/', referrer: 'https://site.test/', storage: { [KEY]: JSON.stringify({ path: '/', referrer: '', utm_source: 'google' }) } });
  var ctx = formContext(null);
  assert.equal(ctx.page, '/contact/');
  assert.deepEqual(ctx.landing, { path: '/', referrer: '', utm_source: 'google' });
  assert.equal(ctx.locale, 'fr-FR');
  assert.equal(ctx.device, 'desktop');
});

test('formContext uses the current page when nothing is stored', function () {
  setEnv({ url: 'https://site.test/contact/' });
  assert.equal(formContext(null).landing.path, '/contact/');
});

test('formContext uses the current page when it starts a new visit', function () {
  setEnv({ url: 'https://site.test/promo/?utm_source=bing', storage: { [KEY]: JSON.stringify({ path: '/', referrer: '' }) } });
  var landing = formContext(null).landing;
  assert.equal(landing.path, '/promo/');
  assert.equal(landing.utm_source, 'bing');
});

test('formContext rounds seconds from startedAt, null without it', function () {
  setEnv();
  assert.equal(formContext(Date.now() - 12600).seconds, 13);
  assert.equal(formContext(null).seconds, null);
  assert.equal(formContext(0).seconds, null);
});

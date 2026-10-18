import { test } from 'node:test';
import assert from 'node:assert/strict';
import { requestUrl } from '../../assets/src/lib/request-url.js';

test('adds the page language when the site has one', () => {
  assert.equal(requestUrl('/wp-json/lcmt-mailer/v1/forms/contact', { lang: 'en' }), '/wp-json/lcmt-mailer/v1/forms/contact?lang=en');
});

test('leaves the URL alone without a language', () => {
  assert.equal(requestUrl('/wp-json/lcmt-mailer/v1/forms/contact', { lang: '' }), '/wp-json/lcmt-mailer/v1/forms/contact');
  assert.equal(requestUrl('/wp-json/lcmt-mailer/v1/forms/contact', {}), '/wp-json/lcmt-mailer/v1/forms/contact');
  assert.equal(requestUrl('/wp-json/lcmt-mailer/v1/forms/contact', undefined), '/wp-json/lcmt-mailer/v1/forms/contact');
});

test('keeps an existing query string', () => {
  assert.equal(requestUrl('/forms/contact?a=1', { lang: 'en' }), '/forms/contact?a=1&lang=en');
});

test('encodes the language', () => {
  assert.equal(requestUrl('/forms/contact', { lang: 'pt br&x=1' }), '/forms/contact?lang=pt%20br%26x%3D1');
});

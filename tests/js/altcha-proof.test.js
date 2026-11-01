import { test } from 'node:test';
import assert from 'node:assert/strict';
import { expiresSoon } from '../../assets/src/lib/captcha/altcha-proof.js';

var NOW = Date.UTC(2026, 10, 1, 12, 0, 0);

/** An ALTCHA payload whose salt carries the challenge expiry, as the server makes it. */
function proof(expiresAtMs) {
  var salt = 'abc123?expires=' + Math.floor(expiresAtMs / 1000);
  return Buffer.from(JSON.stringify({ algorithm: 'SHA-256', salt: salt, number: 42 })).toString('base64');
}

test('a proof expiring in 10 s expires soon', () => {
  assert.equal(expiresSoon(proof(NOW + 10000), NOW), true);
});

test('a proof expiring in 5 min does not', () => {
  assert.equal(expiresSoon(proof(NOW + 300000), NOW), false);
});

test('an unreadable proof expires soon', () => {
  assert.equal(expiresSoon('not base64 json', NOW), true);
  assert.equal(expiresSoon(Buffer.from(JSON.stringify({ salt: 'no-expiry' })).toString('base64'), NOW), true);
  assert.equal(expiresSoon('', NOW), true);
});

test('the margin is adjustable', () => {
  assert.equal(expiresSoon(proof(NOW + 10000), NOW, 5000), false);
});

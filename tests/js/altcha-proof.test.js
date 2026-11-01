import { test } from 'node:test';
import assert from 'node:assert/strict';
import { expiresSoon } from '../../assets/src/lib/captcha/altcha-proof.js';

var NOW = Date.UTC(2026, 10, 1, 12, 0, 0);

/** An ALTCHA payload: the challenge parameters carry the expiry in Unix seconds. */
function proof(expiresAtMs) {
  var payload = { challenge: { parameters: { expiresAt: Math.floor(expiresAtMs / 1000) } }, solution: {} };
  return Buffer.from(JSON.stringify(payload)).toString('base64');
}

test('a proof expiring in 10 min does not expire soon', () => {
  assert.equal(expiresSoon(proof(NOW + 600000), NOW), false);
});

test('a proof expiring in 10 s expires soon', () => {
  assert.equal(expiresSoon(proof(NOW + 10000), NOW), true);
});

test('the widget test payload expires soon', () => {
  var test = Buffer.from(JSON.stringify({ challenge: null, solution: null, test: true })).toString('base64');
  assert.equal(expiresSoon(test, NOW), true);
});

test('a v1 proof is no longer read and expires soon', () => {
  var v1 = Buffer.from(JSON.stringify({ algorithm: 'SHA-256', salt: 'abc?expires=' + Math.floor((NOW + 300000) / 1000), number: 42 })).toString('base64');
  assert.equal(expiresSoon(v1, NOW), true);
});

test('garbage expires soon', () => {
  assert.equal(expiresSoon('%%%', NOW), true);
  assert.equal(expiresSoon('', NOW), true);
  assert.equal(expiresSoon(Buffer.from('[]').toString('base64'), NOW), true);
});

test('the margin is adjustable', () => {
  assert.equal(expiresSoon(proof(NOW + 10000), NOW, 5000), false);
});

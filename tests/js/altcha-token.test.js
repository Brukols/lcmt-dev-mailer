import { test } from 'node:test';
import assert from 'node:assert/strict';
import { altchaToken } from '../../assets/src/lib/captcha/altcha-token.js';

/** A proof expiring in 10 min, so the adapter keeps it. */
function proof(tag) {
  var expiresAt = Math.floor(Date.now() / 1000) + 600;
  return Buffer.from(JSON.stringify({ challenge: { parameters: { expiresAt: expiresAt }, tag: tag }, solution: {} })).toString('base64');
}

/**
 * An altcha-widget stand-in: events, getState(), reset() and verify(), and
 * the hidden input of its form. verify() and reset() are only counted, the
 * test drives the events.
 */
function fakeWidget(state, value) {
  var widget = new EventTarget();
  var input = { value: value || '' };
  var form = {
    querySelector: function (selector) { return selector === 'input[name="altcha"]' ? input : null; },
  };

  widget.state = state;
  widget.calls = [];
  widget.input = input;
  widget.closest = function (selector) { return selector === 'form' ? form : null; };
  widget.getState = function () { return widget.state; };
  widget.reset = function () { widget.calls.push('reset'); widget.state = 'unverified'; };
  widget.verify = function () { widget.calls.push('verify'); widget.state = 'verifying'; };
  widget.emit = function (type, detail) {
    if (detail && detail.state) widget.state = detail.state;
    widget.dispatchEvent(new CustomEvent(type, { detail: detail }));
  };

  return widget;
}

test('a valid proof in the form is sent as is', async () => {
  var payload = proof('kept');
  var widget = fakeWidget('verified', payload);

  assert.equal(await altchaToken(widget), payload);
  assert.deepEqual(widget.calls, []);
});

test('a verification in progress is awaited, not restarted', async () => {
  var widget = fakeWidget('verifying');
  var payload = proof('in-progress');
  var pending = altchaToken(widget);

  widget.emit('statechange', { state: 'verified', payload: payload });
  widget.emit('verified', { payload: payload });

  assert.equal(await pending, payload);
  assert.deepEqual(widget.calls, []);
});

test('a verification in progress that fails rejects', async () => {
  var widget = fakeWidget('verifying');
  var pending = altchaToken(widget);

  widget.emit('statechange', { state: 'error' });

  await assert.rejects(pending, /ALTCHA failed/);
  assert.deepEqual(widget.calls, []);
});

test('a verification in progress that never ends times out', async () => {
  var widget = fakeWidget('verifying');

  await assert.rejects(altchaToken(widget, 20), /timed out/);
  assert.deepEqual(widget.calls, []);
});

test('an idle widget is reset and solves a new challenge', async () => {
  var widget = fakeWidget('unverified');
  var payload = proof('new');
  var pending = altchaToken(widget);

  assert.deepEqual(widget.calls, ['reset', 'verify']);
  widget.emit('verified', { payload: payload });

  assert.equal(await pending, payload);
});

test('an expiring proof is solved again', async () => {
  var old = Buffer.from(JSON.stringify({ challenge: { parameters: { expiresAt: Math.floor(Date.now() / 1000) + 5 } }, solution: {} })).toString('base64');
  var widget = fakeWidget('verified', old);
  var payload = proof('renewed');
  var pending = altchaToken(widget);

  assert.deepEqual(widget.calls, ['reset', 'verify']);
  widget.emit('verified', { payload: payload });

  assert.equal(await pending, payload);
});

test('an idle widget that fails rejects', async () => {
  var widget = fakeWidget('error');
  var pending = altchaToken(widget);

  widget.emit('statechange', { state: 'error' });

  await assert.rejects(pending, /ALTCHA failed/);
});

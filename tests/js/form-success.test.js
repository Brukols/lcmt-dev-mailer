import { test } from 'node:test';
import assert from 'node:assert/strict';
import { SUCCESS_EVENT, redirectUrl, handleSuccess } from '../../assets/src/lib/form-success.js';

/** A form stand-in: a real EventTarget, plus a parent that sees bubbling events. */
function fakeForm() {
  var form = new EventTarget();
  form.seen = [];
  form.addEventListener(SUCCESS_EVENT, function (e) {
    form.seen.push(e);
  });
  return form;
}

function recorder() {
  var calls = [];
  return {
    calls: calls,
    actions: {
      redirect: function (url) { calls.push(['redirect', url]); },
      showMessage: function () { calls.push(['message']); },
    },
  };
}

test('the event is named lcmt:success', () => {
  assert.equal(SUCCESS_EVENT, 'lcmt:success');
});

test('reads the confirmation page URL of a success answer', () => {
  assert.equal(redirectUrl({ success: true, redirect: 'https://site.test/merci/' }), 'https://site.test/merci/');
  assert.equal(redirectUrl({ success: true, redirect: 'http://site.test/merci/' }), 'http://site.test/merci/');
  assert.equal(redirectUrl({ success: true, redirect: '/merci/' }), '/merci/');
});

test('no usable URL without a redirect key or with a strange value', () => {
  [
    undefined,
    null,
    {},
    { success: true },
    { success: true, redirect: '' },
    { success: true, redirect: 42 },
    { success: true, redirect: 'javascript:alert(1)' },
    { success: true, redirect: ' JavaScript:alert(1)' },
    { success: true, redirect: 'data:text/html,hi' },
    { success: true, redirect: '//evil.test/' },
    { success: true, redirect: 'merci' },
  ].forEach(function (res) {
    assert.equal(redirectUrl(res), '', JSON.stringify(res));
  });
});

test('without a confirmation page, shows the message as before', () => {
  var form = fakeForm();
  var rec = recorder();
  var res = { success: true, message: 'Sent.' };

  assert.equal(handleSuccess(form, res, rec.actions), 'message');
  assert.deepEqual(rec.calls, [['message']]);
});

test('with a confirmation page, redirects instead of showing the message', () => {
  var form = fakeForm();
  var rec = recorder();
  var res = { success: true, message: 'Sent.', redirect: 'https://site.test/merci/' };

  assert.equal(handleSuccess(form, res, rec.actions), 'redirect');
  assert.deepEqual(rec.calls, [['redirect', 'https://site.test/merci/']]);
});

test('dispatches a bubbling, cancelable lcmt:success with the response, before redirecting', () => {
  var form = fakeForm();
  var order = [];
  var res = { success: true, message: 'Sent.', redirect: '/merci/' };

  form.addEventListener(SUCCESS_EVENT, function () { order.push('event'); });

  handleSuccess(form, res, {
    redirect: function () { order.push('redirect'); },
    showMessage: function () { order.push('message'); },
  });

  assert.equal(form.seen.length, 1);
  assert.equal(form.seen[0].bubbles, true);
  assert.equal(form.seen[0].cancelable, true);
  assert.equal(form.seen[0].detail, res);
  assert.deepEqual(order, ['event', 'redirect']);
});

test('dispatches lcmt:success without a confirmation page too', () => {
  var form = fakeForm();
  var res = { success: true, message: 'Sent.' };

  handleSuccess(form, res, recorder().actions);

  assert.equal(form.seen.length, 1);
  assert.equal(form.seen[0].detail, res);
});

test('a listener that cancels the event keeps the visitor on the page', () => {
  var form = fakeForm();
  var rec = recorder();

  form.addEventListener(SUCCESS_EVENT, function (e) { e.preventDefault(); });

  assert.equal(handleSuccess(form, { success: true, message: 'Sent.', redirect: '/merci/' }, rec.actions), 'message');
  assert.deepEqual(rec.calls, [['message']]);
});

test('an unsafe redirect value is ignored', () => {
  var rec = recorder();

  assert.equal(handleSuccess(fakeForm(), { success: true, redirect: 'javascript:alert(1)' }, rec.actions), 'message');
  assert.deepEqual(rec.calls, [['message']]);
});

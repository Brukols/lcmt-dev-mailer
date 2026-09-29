import { test } from 'node:test';
import assert from 'node:assert/strict';
import { deactivateLinkSelector, createDeactivateHandler } from '../../assets/src/lib/uninstall-prompt.js';

var CFG = {
  ajaxUrl: 'https://site.test/wp-admin/admin-ajax.php',
  action: 'lcmt_mailer_set_uninstall_data',
  nonce: 'n0nce',
  plugin: 'lcmt-dev-mailer/lcmt-dev-mailer.php',
  network: '',
  confirm: 'If you later delete it?',
};

/**
 * A link that answers matches() for one selector. click() goes through the
 * handler like a real click reaching the capture listener, and counts a
 * navigation when nothing held it back.
 */
function fakeLink(selector, getHandler) {
  var link = {
    followed: 0,
    matches: function (sel) {
      return sel === selector;
    },
    closest: function () {
      return link;
    },
    click: function () {
      var event = fakeEvent(link);
      getHandler()(event);
      if (!event.defaultPrevented) link.followed++;
      return event;
    },
  };
  return link;
}

function fakeEvent(target) {
  return {
    target: target,
    defaultPrevented: false,
    stopped: false,
    preventDefault: function () {
      this.defaultPrevented = true;
    },
    stopImmediatePropagation: function () {
      this.stopped = true;
    },
  };
}

function setup(answer, fetchResult) {
  var calls = [];
  var handler = createDeactivateHandler(CFG, {
    confirm: function (text) {
      calls.push(['confirm', text]);
      return answer;
    },
    fetch: function (url, init) {
      calls.push(['fetch', url, init.method, String(init.body)]);
      return fetchResult;
    },
  });
  var link = fakeLink(deactivateLinkSelector(CFG.plugin), function () {
    return handler;
  });
  return { calls: calls, handler: handler, link: link };
}

function settle() {
  return new Promise(function (resolve) {
    setTimeout(resolve, 0);
  });
}

test('the selector targets the Deactivate link of this plugin row', function () {
  assert.equal(deactivateLinkSelector('a/b.php'), 'tr[data-plugin="a/b.php"] .deactivate a');
});

test('OK stores 1, then follows the deactivate link once', async function () {
  var s = setup(true, Promise.resolve({ ok: true }));

  var first = s.link.click();
  assert.equal(first.defaultPrevented, true, 'first click held back');
  assert.equal(first.stopped, true);
  assert.equal(s.link.followed, 0);

  await settle();

  assert.deepEqual(s.calls[0], ['confirm', 'If you later delete it?']);
  assert.equal(s.calls[1][1], CFG.ajaxUrl);
  assert.equal(s.calls[1][2], 'POST');
  var body = new URLSearchParams(s.calls[1][3]);
  assert.equal(body.get('action'), CFG.action);
  assert.equal(body.get('nonce'), 'n0nce');
  assert.equal(body.get('delete'), '1');
  assert.equal(body.get('network'), '0');
  assert.equal(s.link.followed, 1, 'deactivation goes on');
  assert.equal(s.calls.length, 2, 'asked once');
});

test('Cancel stores 0 and still deactivates', async function () {
  var s = setup(false, Promise.resolve({ ok: true }));

  s.link.click();
  await settle();

  assert.equal(new URLSearchParams(s.calls[1][3]).get('delete'), '0');
  assert.equal(s.link.followed, 1);
});

test('a double click prompts once and follows the link once', async function () {
  var s = setup(true, Promise.resolve({ ok: true }));

  s.link.click();
  var second = s.link.click();
  assert.equal(second.defaultPrevented, true, 'second click ignored while the request runs');

  await settle();
  var late = s.link.click();

  assert.equal(late.defaultPrevented, true, 'a click after leaving is ignored too');
  assert.equal(s.calls.filter(function (c) { return c[0] === 'confirm'; }).length, 1, 'one prompt');
  assert.equal(s.calls.filter(function (c) { return c[0] === 'fetch'; }).length, 1, 'one request');
  assert.equal(s.link.followed, 1, 'one navigation');
});

test('a failed request still deactivates (the data is kept)', async function () {
  var s = setup(true, Promise.reject(new Error('offline')));

  s.link.click();
  await settle();

  assert.equal(s.link.followed, 1);
});

test('clicks on other links are left alone', function () {
  var s = setup(true, Promise.resolve({ ok: true }));
  var other = fakeLink('tr[data-plugin="other/other.php"] .deactivate a', function () {
    return s.handler;
  });

  var event = other.click();

  assert.equal(event.defaultPrevented, false);
  assert.equal(other.followed, 1);
  assert.equal(s.calls.length, 0);
});

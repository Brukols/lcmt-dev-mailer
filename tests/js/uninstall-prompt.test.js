import { test } from 'node:test';
import assert from 'node:assert/strict';
import { deleteLinkSelector, createDeleteHandler } from '../../assets/src/lib/uninstall-prompt.js';

var CFG = {
  ajaxUrl: 'https://site.test/wp-admin/admin-ajax.php',
  action: 'lcmt_mailer_set_uninstall_data',
  nonce: 'n0nce',
  plugin: 'lcmt-dev-mailer/lcmt-dev-mailer.php',
  network: '',
  confirm: 'Also delete?',
};

/**
 * A link that answers matches() for one selector and re-dispatches its
 * click to the handler, like a real click would reach the capture listener.
 */
function fakeLink(selector, handler) {
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
      handler(event);
      if (!event.defaultPrevented) link.followed++;
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
  var env = {
    confirm: function (text) {
      calls.push(['confirm', text]);
      return answer;
    },
    fetch: function (url, init) {
      calls.push(['fetch', url, init.method, String(init.body)]);
      return fetchResult;
    },
  };
  var handler = createDeleteHandler(CFG, env);
  return { calls: calls, handler: handler };
}

test('the selector targets the Delete link of this plugin row', function () {
  assert.equal(deleteLinkSelector('a/b.php'), 'tr[data-plugin="a/b.php"] .delete a');
});

test('OK stores 1, then lets the delete go on without asking again', async function () {
  var s = setup(true, Promise.resolve({ ok: true }));
  var link = fakeLink(deleteLinkSelector(CFG.plugin), s.handler);
  var event = fakeEvent(link);

  var done = s.handler(event);
  assert.equal(event.defaultPrevented, true, 'first click held back');
  assert.equal(event.stopped, true, 'WordPress handlers wait');
  assert.equal(link.followed, 0);

  await done;

  assert.deepEqual(s.calls[0], ['confirm', 'Also delete?']);
  assert.equal(s.calls[1][1], CFG.ajaxUrl);
  assert.equal(s.calls[1][2], 'POST');
  var body = new URLSearchParams(s.calls[1][3]);
  assert.equal(body.get('action'), CFG.action);
  assert.equal(body.get('nonce'), 'n0nce');
  assert.equal(body.get('delete'), '1');
  assert.equal(body.get('network'), '0');
  assert.equal(link.followed, 1, 'delete flow continues');
  assert.equal(s.calls.length, 2, 'asked once');
});

test('Cancel stores 0 and still continues', async function () {
  var s = setup(false, Promise.resolve({ ok: true }));
  var link = fakeLink(deleteLinkSelector(CFG.plugin), s.handler);

  await s.handler(fakeEvent(link));

  assert.equal(new URLSearchParams(s.calls[1][3]).get('delete'), '0');
  assert.equal(link.followed, 1);
});

test('a failed request still continues the delete (the data is kept)', async function () {
  var s = setup(true, Promise.reject(new Error('offline')));
  var link = fakeLink(deleteLinkSelector(CFG.plugin), s.handler);

  await s.handler(fakeEvent(link));

  assert.equal(link.followed, 1);
});

test('clicks on other links are left alone', function () {
  var s = setup(true, Promise.resolve({ ok: true }));
  var other = fakeLink('tr[data-plugin="other/other.php"] .delete a', s.handler);
  var event = fakeEvent(other);

  assert.equal(s.handler(event), undefined);
  assert.equal(event.defaultPrevented, false);
  assert.equal(s.calls.length, 0);
});

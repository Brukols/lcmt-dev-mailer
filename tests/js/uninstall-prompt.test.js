import { test } from 'node:test';
import assert from 'node:assert/strict';
import { deactivateLinkSelector, createDeactivateHandler } from '../../assets/src/lib/uninstall-prompt.js';

var CFG = {
  ajaxUrl: 'https://site.test/wp-admin/admin-ajax.php',
  action: 'lcmt_mailer_set_uninstall_data',
  nonce: 'n0nce',
  plugin: 'lcmt-dev-mailer/lcmt-dev-mailer.php',
  network: '',
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

/**
 * The dialog is a stand-in: open() records the handlers the test then calls
 * like the buttons would.
 */
function setup(fetchResult) {
  var calls = [];
  var prompt = {
    handlers: null,
    opened: 0,
    open: function (handlers) {
      this.opened++;
      this.handlers = handlers;
    },
    setBusy: function (busy) {
      calls.push(['busy', busy]);
    },
  };
  var handler = createDeactivateHandler(CFG, {
    prompt: prompt,
    fetch: function (url, init) {
      calls.push(['fetch', url, init.method, String(init.body)]);
      return fetchResult;
    },
  });
  var link = fakeLink(deactivateLinkSelector(CFG.plugin), function () {
    return handler;
  });
  return { calls: calls, handler: handler, link: link, prompt: prompt };
}

function settle() {
  return new Promise(function (resolve) {
    setTimeout(resolve, 0);
  });
}

function fetches(s) {
  return s.calls.filter(function (c) {
    return c[0] === 'fetch';
  });
}

test('the selector targets the Deactivate link of this plugin row', function () {
  assert.equal(deactivateLinkSelector('a/b.php'), 'tr[data-plugin="a/b.php"] .deactivate a');
});

test('a click on Deactivate opens the dialog and holds the link back', function () {
  var s = setup(Promise.resolve({ ok: true }));

  var first = s.link.click();

  assert.equal(first.defaultPrevented, true);
  assert.equal(first.stopped, true);
  assert.equal(s.prompt.opened, 1);
  assert.equal(s.link.followed, 0, 'nothing navigates yet');
  assert.equal(fetches(s).length, 0, 'nothing is sent yet');
});

test('Cancel sends nothing, does not deactivate, and the dialog can open again', async function () {
  var s = setup(Promise.resolve({ ok: true }));

  s.link.click();
  s.prompt.handlers.onCancel();
  await settle();

  assert.equal(fetches(s).length, 0, 'no request');
  assert.equal(s.link.followed, 0, 'no navigation');

  s.link.click();
  assert.equal(s.prompt.opened, 2, 'opens again');
});

test('Deactivate with the box ticked stores 1, then follows the link once', async function () {
  var s = setup(Promise.resolve({ ok: true }));

  s.link.click();
  s.prompt.handlers.onConfirm(true);

  assert.deepEqual(s.calls[0], ['busy', true], 'busy at once');
  assert.equal(s.link.followed, 0, 'not before the request is done');

  await settle();

  var body = new URLSearchParams(fetches(s)[0][3]);
  assert.equal(fetches(s)[0][1], CFG.ajaxUrl);
  assert.equal(fetches(s)[0][2], 'POST');
  assert.equal(body.get('action'), CFG.action);
  assert.equal(body.get('nonce'), 'n0nce');
  assert.equal(body.get('delete'), '1');
  assert.equal(body.get('network'), '0');
  assert.equal(s.link.followed, 1, 'deactivation goes on');
});

test('Deactivate with the box unticked stores 0', async function () {
  var s = setup(Promise.resolve({ ok: true }));

  s.link.click();
  s.prompt.handlers.onConfirm(false);
  await settle();

  assert.equal(new URLSearchParams(fetches(s)[0][3]).get('delete'), '0');
  assert.equal(s.link.followed, 1);
});

test('the network admin sends network=1', async function () {
  var calls = [];
  var prompt = {
    open: function (handlers) {
      this.handlers = handlers;
    },
    setBusy: function () {},
  };
  var handler = createDeactivateHandler(Object.assign({}, CFG, { network: true }), {
    prompt: prompt,
    fetch: function (url, init) {
      calls.push(String(init.body));
      return Promise.resolve({ ok: true });
    },
  });
  var link = fakeLink(deactivateLinkSelector(CFG.plugin), function () {
    return handler;
  });

  link.click();
  prompt.handlers.onConfirm(true);
  await settle();

  assert.equal(new URLSearchParams(calls[0]).get('network'), '1');
});

test('a double submit sends one request and follows the link once', async function () {
  var s = setup(Promise.resolve({ ok: true }));

  s.link.click();
  s.prompt.handlers.onConfirm(true);
  s.prompt.handlers.onConfirm(true);

  var during = s.link.click();
  assert.equal(during.defaultPrevented, true, 'a click on the link while the request runs is ignored');

  await settle();
  var late = s.link.click();

  assert.equal(late.defaultPrevented, true, 'a click after leaving is ignored too');
  assert.equal(s.prompt.opened, 1, 'the dialog did not open again');
  assert.equal(fetches(s).length, 1, 'one request');
  assert.equal(s.link.followed, 1, 'one navigation');
});

test('Cancel while the request runs changes nothing', async function () {
  var s = setup(Promise.resolve({ ok: true }));

  s.link.click();
  s.prompt.handlers.onConfirm(true);
  s.prompt.handlers.onCancel();
  await settle();

  assert.equal(s.link.followed, 1, 'still deactivates');
  assert.equal(fetches(s).length, 1);
});

test('a failed request stores nothing and still deactivates', async function () {
  var s = setup(Promise.reject(new Error('offline')));

  s.link.click();
  s.prompt.handlers.onConfirm(true);
  await settle();

  assert.equal(s.link.followed, 1);
});

test('clicks on other links are left alone', function () {
  var s = setup(Promise.resolve({ ok: true }));
  var other = fakeLink('tr[data-plugin="other/other.php"] .deactivate a', function () {
    return s.handler;
  });

  var event = other.click();

  assert.equal(event.defaultPrevented, false);
  assert.equal(other.followed, 1);
  assert.equal(s.prompt.opened, 0);
});

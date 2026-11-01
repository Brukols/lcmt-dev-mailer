import { test } from 'node:test';
import assert from 'node:assert/strict';
import { API_URL, createTurnstileAdapter, bindLazyRender, apiLoader, turnstileLanguage } from '../../assets/src/lib/captcha/turnstile-widget.js';

/**
 * A turnstile stand-in: records render/reset calls, and the test drives the
 * callbacks of each widget through `fire()`.
 */
function fakeTurnstile() {
  var api = {
    rendered: [],
    resets: [],
    expired: {},
    render: function (el, options) {
      var id = 'w' + (api.rendered.length + 1);
      api.rendered.push({ el: el, options: options, id: id });
      return id;
    },
    reset: function (id) { api.resets.push(id); },
    isExpired: function (id) { return !!api.expired[id]; },
    fire: function (name, value, index) {
      api.rendered[index || 0].options[name](value);
    },
  };
  return api;
}

function fakeWidget(attrs) {
  var values = Object.assign({
    'data-lcmt-captcha': 'turnstile',
    'data-sitekey': '1x00000000000000000000AA',
    'data-action': 'lcmt_form',
    'data-appearance': 'interaction-only',
  }, attrs || {});

  return {
    ownerDocument: { documentElement: { lang: 'fr-FR' } },
    getAttribute: function (name) {
      return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : null;
    },
  };
}

function setup(timeoutMs) {
  var api = fakeTurnstile();
  var loads = { count: 0 };
  var adapter = createTurnstileAdapter({
    loadApi: function () { loads.count++; return Promise.resolve(api); },
    timeoutMs: timeoutMs || 1000,
  });
  return { api: api, loads: loads, adapter: adapter };
}

/** Let the pending promise callbacks run. */
function flush() {
  return new Promise(function (resolve) { setTimeout(resolve, 0); });
}

test('the widget language comes from <html lang> when Turnstile can take it', () => {
  assert.equal(turnstileLanguage('fr-FR'), 'fr-fr');
  assert.equal(turnstileLanguage('fr'), 'fr');
  assert.equal(turnstileLanguage('x-klingon'), 'auto');
  assert.equal(turnstileLanguage(''), 'auto');
  assert.equal(turnstileLanguage(undefined), 'auto');
});

test('the adapter has the turnstile id and field', () => {
  var adapter = setup().adapter;

  assert.equal(adapter.id, 'turnstile');
  assert.equal(adapter.field, 'turnstile');
  assert.equal(API_URL, 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=lcmtTurnstileReady');
});

test('does not load the API before the visitor enters the form', async () => {
  var s = setup();
  var form = new EventTarget();

  bindLazyRender(form, fakeWidget(), s.adapter);
  await flush();

  assert.equal(s.loads.count, 0);
  assert.equal(s.api.rendered.length, 0);
});

/** A window whose IntersectionObserver the test triggers by hand. */
function viewportRoot() {
  var root = { observers: [] };

  root.IntersectionObserver = function (callback) {
    var observer = { callback: callback, targets: [], disconnected: false };
    observer.observe = function (el) { observer.targets.push(el); };
    observer.disconnect = function () { observer.disconnected = true; };
    root.observers.push(observer);
    return observer;
  };

  return root;
}

test('an interaction-only widget does not watch the viewport', async () => {
  var s = setup();
  var root = viewportRoot();

  bindLazyRender(new EventTarget(), fakeWidget(), s.adapter, root);
  await flush();

  assert.equal(root.observers.length, 0);
  assert.equal(s.loads.count, 0);
});

test('an always visible widget renders once it scrolls into view, not before', async () => {
  var s = setup();
  var root = viewportRoot();
  var widget = fakeWidget({ 'data-appearance': 'always' });

  bindLazyRender(new EventTarget(), widget, s.adapter, root);
  await flush();
  assert.equal(s.loads.count, 0);

  var observer = root.observers[0];
  assert.deepEqual(observer.targets, [widget]);

  observer.callback([{ isIntersecting: false }]);
  await flush();
  assert.equal(s.api.rendered.length, 0);

  observer.callback([{ isIntersecting: true }]);
  await flush();
  assert.equal(s.api.rendered.length, 1);
  assert.equal(observer.disconnected, true);
});

test('an always visible widget renders right away without IntersectionObserver', async () => {
  var s = setup();

  bindLazyRender(new EventTarget(), fakeWidget({ 'data-appearance': 'always' }), s.adapter, {});
  await flush();

  assert.equal(s.api.rendered.length, 1);
});

test('renders once on the first focusin, with response-field false and the data-* options', async () => {
  var s = setup();
  var form = new EventTarget();
  var widget = fakeWidget();

  bindLazyRender(form, widget, s.adapter);
  form.dispatchEvent(new Event('focusin'));
  form.dispatchEvent(new Event('focusin'));
  await flush();

  assert.equal(s.api.rendered.length, 1);

  var call = s.api.rendered[0];
  assert.equal(call.el, widget);
  assert.equal(call.options.sitekey, '1x00000000000000000000AA');
  assert.equal(call.options.action, 'lcmt_form');
  assert.equal(call.options.appearance, 'interaction-only');
  assert.equal(call.options['response-field'], false);
  assert.equal(call.options['refresh-expired'], 'auto');
  assert.equal(call.options.language, 'fr-fr');
  assert.equal(typeof call.options.callback, 'function');
  assert.equal(typeof call.options['expired-callback'], 'function');
  assert.equal(typeof call.options['error-callback'], 'function');

  // A submit after the focus uses the same widget.
  var pending = s.adapter.token(widget);
  await flush();
  s.api.fire('callback', 'tok-1');

  assert.equal(await pending, 'tok-1');
  assert.equal(s.api.rendered.length, 1);
});

test('token renders the widget when the visitor never focused a field', async () => {
  var s = setup();
  var widget = fakeWidget();
  var pending = s.adapter.token(widget);

  await flush();
  assert.equal(s.api.rendered.length, 1);
  // A fresh widget runs its challenge itself: no reset.
  assert.deepEqual(s.api.resets, []);

  s.api.fire('callback', 'tok-1');
  assert.equal(await pending, 'tok-1');
});

test('resolves the current token when it is still valid', async () => {
  var s = setup();
  var widget = fakeWidget();

  await s.adapter.render(widget);
  s.api.fire('callback', 'tok-1');

  assert.equal(await s.adapter.token(widget), 'tok-1');
  assert.deepEqual(s.api.resets, []);
});

test('waits for a new token when the current one expired', async () => {
  var s = setup();
  var widget = fakeWidget();

  await s.adapter.render(widget);
  s.api.fire('callback', 'tok-1');
  s.api.expired.w1 = true;

  var pending = s.adapter.token(widget);
  await flush();

  assert.deepEqual(s.api.resets, ['w1']);

  s.api.expired.w1 = false;
  s.api.fire('callback', 'tok-2');
  assert.equal(await pending, 'tok-2');
});

test('waits when the expired-callback dropped the token', async () => {
  var s = setup();
  var widget = fakeWidget();

  await s.adapter.render(widget);
  s.api.fire('callback', 'tok-1');
  s.api.fire('expired-callback');

  var pending = s.adapter.token(widget);
  await flush();
  // refresh-expired: auto already fetches a new one: just wait for it.
  s.api.fire('callback', 'tok-2');

  assert.equal(await pending, 'tok-2');
});

test('reset clears the token so the next submit waits for a new one', async () => {
  var s = setup();
  var widget = fakeWidget();

  await s.adapter.render(widget);
  s.api.fire('callback', 'tok-1');
  s.adapter.reset(widget);

  assert.deepEqual(s.api.resets, ['w1']);

  var settled = false;
  var pending = s.adapter.token(widget).then(function (t) { settled = true; return t; });
  await flush();
  assert.equal(settled, false);

  s.api.fire('callback', 'tok-2');
  assert.equal(await pending, 'tok-2');
});

test('reset before the widget renders does nothing', () => {
  var s = setup();

  assert.doesNotThrow(function () { s.adapter.reset(fakeWidget()); });
  assert.deepEqual(s.api.resets, []);
});

test('rejects on error-callback', async () => {
  var s = setup();
  var widget = fakeWidget();
  var pending = s.adapter.token(widget);

  await flush();
  s.api.fire('error-callback', '300030');

  await assert.rejects(pending, /Turnstile/);

  // The next submit starts the failed widget over.
  var retry = s.adapter.token(widget);
  await flush();
  assert.deepEqual(s.api.resets, ['w1']);
  s.api.fire('callback', 'tok-2');
  assert.equal(await retry, 'tok-2');
});

test('rejects when api.js never loads', async () => {
  var adapter = createTurnstileAdapter({
    loadApi: function () { return new Promise(function () {}); },
    timeoutMs: 50,
  });

  await assert.rejects(adapter.token(fakeWidget()), /Turnstile/);
});

test('each widget gets its own widget id', async () => {
  var s = setup();
  var first = fakeWidget();
  var second = fakeWidget();

  var a = s.adapter.token(first);
  var b = s.adapter.token(second);
  await flush();

  assert.equal(s.api.rendered.length, 2);
  s.api.fire('callback', 'tok-b', 1);
  s.api.fire('callback', 'tok-a', 0);

  assert.equal(await a, 'tok-a');
  assert.equal(await b, 'tok-b');

  s.adapter.reset(second);
  assert.deepEqual(s.api.resets, ['w2']);
});

/** A window stand-in for the api.js loader. */
function fakeRoot() {
  var appended = [];
  return {
    appended: appended,
    document: {
      head: { appendChild: function (el) { appended.push(el); } },
      createElement: function (tag) { return { tagName: tag.toUpperCase() }; },
    },
  };
}

test('several forms share one api.js load, resolved by lcmtTurnstileReady', async () => {
  var root = fakeRoot();
  var load = apiLoader(root);
  var a = load();
  var b = load();

  assert.equal(root.appended.length, 1);
  assert.equal(root.appended[0].tagName, 'SCRIPT');
  assert.equal(root.appended[0].src, API_URL);

  root.turnstile = fakeTurnstile();
  root.lcmtTurnstileReady();

  assert.equal(await a, root.turnstile);
  assert.equal(await b, root.turnstile);
  assert.equal(await load(), root.turnstile);
  assert.equal(root.appended.length, 1);
});

test('a failed api.js load rejects, and the next call tries again', async () => {
  var root = fakeRoot();
  var load = apiLoader(root);
  var first = load();

  root.appended[0].onerror();
  await assert.rejects(first, /Turnstile/);

  load();
  assert.equal(root.appended.length, 2);
});

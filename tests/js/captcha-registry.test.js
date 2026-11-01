import { test } from 'node:test';
import assert from 'node:assert/strict';
import { defineAdapter, registerAdapter, captchaToken, resetCaptcha } from '../../assets/src/lib/captcha/registry.js';
import { scriptBase, adapterLoader } from '../../assets/src/lib/captcha/script-loader.js';

/** A window stand-in: the registry only needs a document to fire events on. */
function fakeRoot() {
  var doc = new EventTarget();
  doc.appended = [];
  doc.head = {
    appendChild: function (el) { doc.appended.push(el); },
  };
  doc.createElement = function (tag) {
    return { tagName: tag.toUpperCase() };
  };
  // Scripts already on the page: their src, matched like script[src*="…"].
  doc.scripts = [];
  doc.querySelector = function (selector) {
    var match = /^script\[src\*="([^"]+)"\]$/.exec(selector);
    return match ? doc.scripts.find(function (src) { return src.indexOf(match[1]) !== -1; }) || null : null;
  };
  return { document: doc };
}

/** A widget stand-in, either a [data-lcmt-captcha] element or a legacy altcha-widget. */
function fakeWidget(id, tag) {
  return {
    tagName: (tag || 'div').toUpperCase(),
    getAttribute: function (name) {
      return name === 'data-lcmt-captcha' ? id : null;
    },
  };
}

/** A form stand-in answering the two selectors the registry asks for. */
function fakeForm(widget) {
  return {
    querySelector: function (selector) {
      if (!widget) return null;
      if (selector === '[data-lcmt-captcha]') return widget.getAttribute('data-lcmt-captcha') ? widget : null;
      if (selector === 'altcha-widget') return widget.tagName === 'ALTCHA-WIDGET' ? widget : null;
      return null;
    },
  };
}

function adapter(id, overrides) {
  return Object.assign({
    id: id,
    field: id + '-field',
    token: function () { return Promise.resolve(id + '-token'); },
    reset: function () {},
  }, overrides || {});
}

test('defineAdapter throws on a missing token', () => {
  var incomplete = adapter('altcha');
  delete incomplete.token;

  assert.throws(function () { defineAdapter(incomplete); }, function (err) {
    return err instanceof TypeError && /token/.test(err.message);
  });
});

test('defineAdapter returns a frozen copy', () => {
  var source = adapter('altcha');
  var defined = defineAdapter(source);

  assert.notEqual(defined, source);
  assert.ok(Object.isFrozen(defined));
  assert.equal(defined.id, 'altcha');
  assert.equal(defined.field, 'altcha-field');
});

test('resolves null without a widget', async () => {
  assert.equal(await captchaToken(fakeForm(null), fakeRoot()), null);
});

test('uses the adapter named by data-lcmt-captcha', async () => {
  var root = fakeRoot();
  registerAdapter(adapter('altcha'), root);
  registerAdapter(adapter('turnstile'), root);

  assert.deepEqual(await captchaToken(fakeForm(fakeWidget('turnstile')), root), {
    field: 'turnstile-field',
    token: 'turnstile-token',
  });
});

test('passes the widget to the adapter', async () => {
  var root = fakeRoot();
  var widget = fakeWidget('turnstile');
  var seen = null;

  registerAdapter(adapter('turnstile', {
    token: function (w) { seen = w; return Promise.resolve('t'); },
  }), root);

  await captchaToken(fakeForm(widget), root);
  assert.equal(seen, widget);
});

test('finds a legacy altcha-widget without data-lcmt-captcha', async () => {
  var root = fakeRoot();
  registerAdapter(adapter('altcha'), root);

  assert.deepEqual(await captchaToken(fakeForm(fakeWidget(null, 'altcha-widget')), root), {
    field: 'altcha-field',
    token: 'altcha-token',
  });
});

test('registerAdapter announces the adapter on the document', () => {
  var root = fakeRoot();
  var ids = [];

  root.document.addEventListener('lcmt:captcha-ready', function (e) { ids.push(e.detail.id); });
  registerAdapter(adapter('turnstile'), root);

  assert.deepEqual(ids, ['turnstile']);
  assert.equal(root.lcmtMailerCaptchas.turnstile.field, 'turnstile-field');
});

test('waits for an adapter registered after submit', async () => {
  var root = fakeRoot();
  var pending = captchaToken(fakeForm(fakeWidget('turnstile')), root, 1000);

  registerAdapter(adapter('altcha'), root);
  registerAdapter(adapter('turnstile'), root);

  assert.deepEqual(await pending, { field: 'turnstile-field', token: 'turnstile-token' });
});

test('rejects after the timeout when no adapter registers', async () => {
  await assert.rejects(captchaToken(fakeForm(fakeWidget('turnstile')), fakeRoot(), 20));
});

test('rejects when the adapter cannot give a token', async () => {
  var root = fakeRoot();
  registerAdapter(adapter('turnstile', {
    token: function () { return Promise.reject(new Error('failed')); },
  }), root);

  await assert.rejects(captchaToken(fakeForm(fakeWidget('turnstile')), root), /failed/);
});

test('reset calls the adapter of the form\'s widget only', () => {
  var root = fakeRoot();
  var calls = [];
  var widget = fakeWidget('turnstile');

  registerAdapter(adapter('altcha', { reset: function (w) { calls.push(['altcha', w]); } }), root);
  registerAdapter(adapter('turnstile', { reset: function (w) { calls.push(['turnstile', w]); } }), root);

  resetCaptcha(fakeForm(widget), root);
  resetCaptcha(fakeForm(null), root);

  assert.deepEqual(calls, [['turnstile', widget]]);
});

test('reset does nothing before the adapter registers', () => {
  assert.doesNotThrow(function () { resetCaptcha(fakeForm(fakeWidget('turnstile')), fakeRoot()); });
});

test('asks loadAdapter for a missing adapter once', async () => {
  var root = fakeRoot();
  var form = fakeForm(fakeWidget('turnstile'));
  var asked = [];

  function load(id) { asked.push(id); }

  await assert.rejects(captchaToken(form, root, 10, load));
  await assert.rejects(captchaToken(form, root, 10, load));

  assert.deepEqual(asked, ['turnstile']);

  // A registered adapter is never asked for.
  registerAdapter(adapter('altcha'), root);
  await captchaToken(fakeForm(fakeWidget('altcha')), root, 10, load);
  assert.deepEqual(asked, ['turnstile']);
});

test('the dist base is the script folder, without the query string', () => {
  assert.equal(
    scriptBase('https://site.test/wp-content/plugins/lcmt-dev-mailer/assets/dist/form-handler.js?ver=1a2b3c4d'),
    'https://site.test/wp-content/plugins/lcmt-dev-mailer/assets/dist/'
  );
  assert.equal(scriptBase('https://site.test/dist/form-handler.js#x'), 'https://site.test/dist/');
  assert.equal(scriptBase(''), '');
  assert.equal(scriptBase(undefined), '');
});

test('the loader appends each adapter script once, and refuses odd ids', () => {
  var root = fakeRoot();
  var load = adapterLoader('https://site.test/dist/', root.document);

  load('turnstile');
  load('turnstile');
  load('../evil');

  assert.equal(root.document.appended.length, 1);
  assert.equal(root.document.appended[0].tagName, 'SCRIPT');
  assert.equal(root.document.appended[0].src, 'https://site.test/dist/captcha-turnstile.js');
  assert.equal(root.document.appended[0].defer, true);
});

test('the loader leaves alone a script the page already has', () => {
  var root = fakeRoot();
  root.document.scripts.push('https://site.test/dist/captcha-altcha.js?ver=1a2b3c4d');

  adapterLoader('https://site.test/dist/', root.document)('altcha');

  assert.equal(root.document.appended.length, 0);
});

test('a legacy altcha-widget page loads captcha-altcha.js', async () => {
  // HTML cached before the adapters: an <altcha-widget> without
  // data-lcmt-captcha and no <script> for captcha-altcha.js.
  var root = fakeRoot();
  var base = scriptBase('https://site.test/wp-content/plugins/lcmt-dev-mailer/assets/dist/form-handler.js?ver=1a2b3c4d');
  var pending = captchaToken(fakeForm(fakeWidget(null, 'altcha-widget')), root, 1000, adapterLoader(base, root.document));

  assert.equal(root.document.appended.length, 1);
  assert.equal(root.document.appended[0].src, 'https://site.test/wp-content/plugins/lcmt-dev-mailer/assets/dist/captcha-altcha.js');

  // The script runs and registers its adapter.
  registerAdapter(adapter('altcha'), root);

  assert.deepEqual(await pending, { field: 'altcha-field', token: 'altcha-token' });
});

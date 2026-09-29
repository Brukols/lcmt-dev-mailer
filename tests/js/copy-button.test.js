import { test } from 'node:test';
import assert from 'node:assert/strict';
import { copyText, initCopyButtons, RESET_DELAY } from '../../assets/src/lib/copy-button.js';

function fakeButton(text, label, copied) {
  var button = {
    textContent: label,
    attrs: { 'data-lcmt-copy': text, 'data-copied': copied },
    handler: null,
    getAttribute: function (name) {
      return this.attrs[name] === undefined ? null : this.attrs[name];
    },
    addEventListener: function (type, fn) {
      if (type === 'click') this.handler = fn;
    },
  };
  return button;
}

function setup(envOverrides) {
  var buttons = [fakeButton('[a]', 'Copy', 'Copied'), fakeButton('[b]', 'Copy', 'Copied')];
  var status = { textContent: '' };
  var root = {
    querySelectorAll: function () {
      return buttons;
    },
    querySelector: function () {
      return status;
    },
  };
  var timers = [];
  var copied = [];
  var fallbacks = [];
  var env = Object.assign(
    {
      clipboard: {
        writeText: function (text) {
          copied.push(text);
          return Promise.resolve();
        },
      },
      fallback: function (text) {
        fallbacks.push(text);
        return true;
      },
      setTimeout: function (fn, ms) {
        timers.push({ fn: fn, ms: ms, cleared: false });
        return timers.length - 1;
      },
      clearTimeout: function (id) {
        timers[id].cleared = true;
      },
    },
    envOverrides
  );

  initCopyButtons(root, env);

  return { buttons: buttons, status: status, timers: timers, copied: copied, fallbacks: fallbacks };
}

function flush() {
  return new Promise(function (resolve) {
    setImmediate(resolve);
  });
}

test('copies through the Clipboard API', async () => {
  var ok = await copyText('hello', {
    clipboard: { writeText: async function () {} },
    fallback: function () {
      assert.fail('fallback not needed');
    },
  });
  assert.equal(ok, true);
});

test('falls back when the Clipboard API is missing', async () => {
  var seen = [];
  var ok = await copyText('hello', {
    fallback: function (text) {
      seen.push(text);
      return true;
    },
  });
  assert.equal(ok, true);
  assert.deepEqual(seen, ['hello']);
});

test('falls back when the Clipboard API rejects or throws', async () => {
  var seen = [];
  var fallback = function (text) {
    seen.push(text);
    return true;
  };

  assert.equal(await copyText('a', { clipboard: { writeText: () => Promise.reject(new Error('denied')) }, fallback: fallback }), true);
  assert.equal(
    await copyText('b', {
      clipboard: {
        writeText: function () {
          throw new Error('sync');
        },
      },
      fallback: fallback,
    }),
    true
  );
  assert.deepEqual(seen, ['a', 'b']);
});

test('reports a failure when the fallback fails or throws', async () => {
  assert.equal(await copyText('a', { fallback: () => false }), false);
  assert.equal(
    await copyText('a', {
      fallback: function () {
        throw new Error('no');
      },
    }),
    false
  );
});

test('a click copies the button text, shows the confirmation and announces it', async () => {
  var s = setup();

  s.buttons[0].handler();
  await flush();

  assert.deepEqual(s.copied, ['[a]']);
  assert.equal(s.buttons[0].textContent, 'Copied');
  assert.equal(s.buttons[1].textContent, 'Copy');
  assert.equal(s.status.textContent, 'Copied');
  assert.equal(s.timers[0].ms, RESET_DELAY);
});

test('the label and the status come back after the delay', async () => {
  var s = setup();

  s.buttons[0].handler();
  await flush();
  s.timers[0].fn();

  assert.equal(s.buttons[0].textContent, 'Copy');
  assert.equal(s.status.textContent, '');
});

test('a second click restarts the delay', async () => {
  var s = setup();

  s.buttons[0].handler();
  await flush();
  s.buttons[0].handler();
  await flush();

  assert.equal(s.timers[0].cleared, true);
  assert.equal(s.timers[1].cleared, false);
  s.timers[1].fn();
  assert.equal(s.buttons[0].textContent, 'Copy');
});

test('uses the fallback path from a click when there is no Clipboard API', async () => {
  var s = setup({ clipboard: undefined });

  s.buttons[1].handler();
  await flush();

  assert.deepEqual(s.fallbacks, ['[b]']);
  assert.equal(s.buttons[1].textContent, 'Copied');
});

test('a failed copy changes nothing', async () => {
  var s = setup({ clipboard: undefined, fallback: () => false });

  s.buttons[0].handler();
  await flush();

  assert.equal(s.buttons[0].textContent, 'Copy');
  assert.equal(s.status.textContent, '');
  assert.equal(s.timers.length, 0);
});

/**
 * Spam protection adapters, shared by form-handler.js and each
 * captcha-{id}.js script.
 *
 * Every protection ships its own script, loaded only on pages with a form it
 * guards. That script registers an adapter here; the form handler asks the
 * adapter of the form's widget for a token on submit, and resets it after
 * every answer. The adapters live on the window, so the separate bundles
 * share them.
 */

/**
 * @typedef {Object} CaptchaAdapter   The contract every protection implements.
 * @property {string} id              Same id as the PHP provider.
 * @property {string} field           Body key the server reads the token from.
 * @property {(widget: Element) => Promise<string>} token  A token the server will accept now.
 * @property {(widget: Element) => void} reset             Called after every server answer.
 */

export var READY_EVENT = 'lcmt:captcha-ready';

var MEMBERS = { id: 'string', field: 'string', token: 'function', reset: 'function' };

// Adapters already asked for, per window, so a script is requested once.
var requested = new WeakMap();

/**
 * Check an adapter against the contract.
 *
 * @param {CaptchaAdapter} adapter
 * @returns {CaptchaAdapter} A frozen copy.
 */
export function defineAdapter(adapter) {
  var copy = {};

  Object.keys(MEMBERS).forEach(function (name) {
    var value = adapter ? adapter[name] : undefined;

    if (typeof value !== MEMBERS[name] || value === '') {
      throw new TypeError('Captcha adapter: "' + name + '" must be a ' + (MEMBERS[name] === 'string' ? 'non-empty string' : 'function') + '.');
    }

    copy[name] = value;
  });

  return Object.freeze(copy);
}

/**
 * Make an adapter available to the forms of the page.
 */
export function registerAdapter(adapter, root = window) {
  var defined = defineAdapter(adapter);

  root.lcmtMailerCaptchas = root.lcmtMailerCaptchas || {};
  root.lcmtMailerCaptchas[defined.id] = defined;

  root.document.dispatchEvent(new CustomEvent(READY_EVENT, { detail: { id: defined.id } }));

  return defined;
}

/**
 * The form's spam protection widget, with the id of its adapter.
 *
 * Pages cached before adapters existed have an <altcha-widget> without
 * data-lcmt-captcha.
 */
function findWidget(form) {
  var widget = form.querySelector('[data-lcmt-captcha]');

  if (widget) return { element: widget, id: widget.getAttribute('data-lcmt-captcha') };

  widget = form.querySelector('altcha-widget');

  return widget ? { element: widget, id: 'altcha' } : null;
}

function registered(root, id) {
  var adapters = root.lcmtMailerCaptchas;

  return adapters && Object.prototype.hasOwnProperty.call(adapters, id) ? adapters[id] : null;
}

/**
 * Wait for the adapter's script to register it.
 */
function waitForAdapter(root, id, timeoutMs, loadAdapter) {
  return new Promise(function (resolve, reject) {
    var doc = root.document;
    var timer = setTimeout(function () {
      doc.removeEventListener(READY_EVENT, onReady);
      reject(new Error('Captcha adapter "' + id + '" did not load.'));
    }, timeoutMs);

    function onReady(e) {
      if (!e.detail || e.detail.id !== id) return;

      clearTimeout(timer);
      doc.removeEventListener(READY_EVENT, onReady);
      resolve(registered(root, id));
    }

    doc.addEventListener(READY_EVENT, onReady);

    // Its script may be missing from the page, e.g. from a full-page cache
    // older than the adapters: ask for it once.
    if (loadAdapter) {
      var asked = requested.get(root) || {};

      requested.set(root, asked);

      if (!asked[id]) {
        asked[id] = true;
        loadAdapter(id);
      }
    }
  });
}

/**
 * Resolve with the token the form must send, or null without a widget.
 *
 * @param {(id: string) => void} [loadAdapter] Called once per id when its
 *   adapter is not registered yet, to load its script.
 * @returns {Promise<{field: string, token: string} | null>}
 */
export function captchaToken(form, root = window, timeoutMs = 30000, loadAdapter) {
  var widget = findWidget(form);

  if (!widget) return Promise.resolve(null);

  var adapter = registered(root, widget.id);
  var ready = adapter ? Promise.resolve(adapter) : waitForAdapter(root, widget.id, timeoutMs, loadAdapter);

  return ready.then(function (found) {
    return found.token(widget.element).then(function (token) {
      return { field: found.field, token: token };
    });
  });
}

/**
 * The server spends a token as soon as it reads it, even when it then
 * refuses a field, so every answer needs a new one.
 */
export function resetCaptcha(form, root = window) {
  var widget = findWidget(form);
  var adapter = widget ? registered(root, widget.id) : null;

  if (adapter) adapter.reset(widget.element);
}

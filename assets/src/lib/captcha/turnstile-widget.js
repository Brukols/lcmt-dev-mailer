/**
 * Cloudflare Turnstile adapter, used by captcha-turnstile.js.
 *
 * Cloudflare's api.js is only requested once the visitor enters a protected
 * form (or submits it without focusing a field), so a page that merely shows
 * a form never contacts Cloudflare. An "always" visible widget is the
 * exception: it renders as soon as it scrolls into view, so it does not
 * pop up and push the submit button while the visitor types. Each widget is rendered explicitly from
 * the data-* attributes of Turnstile::widget(), and its token is kept in
 * memory: with response-field false, Turnstile adds no hidden input, and the
 * form handler sends the token as the adapter's field.
 */

export var API_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=lcmtTurnstileReady';

/**
 * The Turnstile language for an <html lang> value: a code like "fr" or
 * "fr-FR" in the lower case Turnstile expects ("fr-fr"), anything else left
 * to Cloudflare ("auto").
 */
export function turnstileLanguage(lang) {
  return /^[a-z]{2,3}(-[A-Z]{2})?$/.test(lang || '') ? lang.toLowerCase() : 'auto';
}

/**
 * A loadApi() that appends api.js once per page and resolves with
 * window.turnstile when Cloudflare calls window.lcmtTurnstileReady().
 */
export function apiLoader(root = window) {
  var pending = null;

  return function () {
    if (root.turnstile) return Promise.resolve(root.turnstile);
    if (pending) return pending;

    pending = new Promise(function (resolve, reject) {
      var doc = root.document;
      var script = doc.createElement('script');

      root.lcmtTurnstileReady = function () {
        resolve(root.turnstile);
      };

      script.src = API_URL;
      script.onerror = function () {
        // Blocked or offline: let the next submit try again.
        pending = null;
        reject(new Error('Turnstile: api.js did not load.'));
      };
      (doc.head || doc.body).appendChild(script);
    });

    return pending;
  };
}

/**
 * @param {{ loadApi: () => Promise<Object>, timeoutMs?: number }} options
 * @returns {CaptchaAdapter & { render: (widget: Element) => Promise<Object> }}
 */
export function createTurnstileAdapter({ loadApi, timeoutMs = 30000 }) {
  // Per widget: its Turnstile id, current token and the submits waiting for one.
  var states = new WeakMap();

  function stateOf(widget) {
    var state = states.get(widget);

    if (!state) {
      state = { api: null, id: null, token: '', failed: false, waiters: [], ready: null };
      states.set(widget, state);
    }

    return state;
  }

  function settle(state, error, token) {
    state.waiters.splice(0).forEach(function (waiter) {
      if (error) waiter.reject(error);
      else waiter.resolve(token);
    });
  }

  function options(widget, state) {
    var doc = widget.ownerDocument || document;

    function fail(code) {
      state.token = '';
      state.failed = true;
      settle(state, new Error('Turnstile failed' + (code ? ' (' + code + ')' : '') + '.'));
    }

    return {
      sitekey: widget.getAttribute('data-sitekey') || '',
      action: widget.getAttribute('data-action') || undefined,
      appearance: widget.getAttribute('data-appearance') || undefined,
      // No hidden input: the form handler would send it as a form field.
      'response-field': false,
      'refresh-expired': 'auto',
      language: turnstileLanguage(doc.documentElement && doc.documentElement.lang),
      callback: function (token) {
        state.token = token;
        state.failed = false;
        settle(state, null, token);
      },
      'expired-callback': function () {
        state.token = '';
      },
      'error-callback': fail,
      // An interactive challenge left unsolved.
      'timeout-callback': function () { fail('timeout'); },
    };
  }

  /**
   * Load api.js and render the widget, once.
   */
  function render(widget) {
    var state = stateOf(widget);

    if (!state.ready) {
      state.ready = Promise.resolve().then(loadApi).then(function (api) {
        state.api = api;
        state.id = api.render(widget, options(widget, state));
        return state;
      });

      // A failed load or render is tried again on the next submit.
      state.ready.catch(function () { state.ready = null; });
    }

    return state.ready;
  }

  function isExpired(state) {
    return typeof state.api.isExpired === 'function' && state.api.isExpired(state.id);
  }

  /**
   * The current token while it is valid, else the next one the widget gives.
   */
  function token(widget) {
    return new Promise(function (resolve, reject) {
      var waiter = null;
      var done = false;
      var timer = setTimeout(function () {
        finish(new Error('Turnstile timed out.'));
      }, timeoutMs);

      function finish(error, value) {
        if (done) return;
        done = true;
        clearTimeout(timer);

        var state = states.get(widget);
        if (waiter && state) state.waiters = state.waiters.filter(function (w) { return w !== waiter; });
        waiter = null;

        if (error) reject(error);
        else resolve(value);
      }

      render(widget).then(function (state) {
        // Timed out while api.js was loading.
        if (done) return;
        if (state.token && !isExpired(state)) return finish(null, state.token);

        waiter = {
          resolve: function (value) { waiter = null; finish(null, value); },
          reject: function (error) { waiter = null; finish(error); },
        };
        state.waiters.push(waiter);

        // A fresh or refreshing widget is already solving a challenge: wait.
        // An expired token or a failed widget has to start over.
        if (state.token || state.failed) {
          state.token = '';
          state.failed = false;
          state.api.reset(state.id);
        }
      }, finish);
    });
  }

  /**
   * The server spends a token on every answer: start the widget over.
   */
  function reset(widget) {
    var state = states.get(widget);

    if (!state || !state.api || state.id === null) return;

    state.token = '';
    state.failed = false;
    state.api.reset(state.id);
  }

  return { id: 'turnstile', field: 'turnstile', token: token, reset: reset, render: render };
}

/**
 * Render the widget when the visitor first enters its form, or, for an
 * "always" visible one, when it scrolls into view.
 */
export function bindLazyRender(form, widget, adapter, root = typeof window !== 'undefined' ? window : {}) {
  function render() {
    // A failure is reported by the submit, which tries again.
    adapter.render(widget).catch(function () {});
  }

  form.addEventListener('focusin', render, { once: true });

  if (widget.getAttribute('data-appearance') !== 'always') return;

  if (typeof root.IntersectionObserver !== 'function') {
    render();
    return;
  }

  var observer = new root.IntersectionObserver(function (entries) {
    if (!entries.some(function (entry) { return entry.isIntersecting; })) return;

    observer.disconnect();
    render();
  }, { rootMargin: '200px' });

  observer.observe(widget);
}

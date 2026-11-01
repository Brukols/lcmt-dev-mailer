/**
 * LCMT Mailer — ALTCHA adapter.
 *
 * Loaded on pages with a form protected by ALTCHA (Altcha::enqueueAssets()),
 * or by form-handler.js on a cached page older than this script. Registers
 * the adapter form-handler.js asks for a proof on submit.
 */
import 'altcha';
import { registerAdapter } from './lib/captcha/registry';
import { altchaToken } from './lib/captcha/altcha-token';

(function () {
  'use strict';

  function reset(widget) {
    if (typeof widget.reset === 'function') widget.reset();
  }

  /**
   * Turn off the browser alert the widget raises on a submit while it is
   * verifying (the form handler waits for the proof behind its loader
   * instead). Widgets take their texts from the global i18n store, one entry
   * per language, so every language the widget may pick is emptied.
   */
  function silenceWaitAlert() {
    var i18n = globalThis.$altcha && globalThis.$altcha.i18n;
    if (!i18n) return;

    var languages = {};
    i18n.store.subscribe(function (value) { languages = value; })();

    Object.keys(languages).forEach(function (lang) {
      i18n.set(lang, Object.assign({}, languages[lang], { waitAlert: '' }));
    });
  }

  function init() {
    silenceWaitAlert();

    // Cache-bust the challenge URL to prevent stale cached responses. A page
    // cached with the 2.7 markup only has `challengeurl`: copy it into
    // `challenge`, the attribute of the v3 widget, before the widget reads it.
    var widgets = document.querySelectorAll('altcha-widget');

    for (var i = 0; i < widgets.length; i++) {
      var url = widgets[i].getAttribute('challenge') || widgets[i].getAttribute('challengeurl');
      if (url && /^(https?:)?\//.test(url)) {
        widgets[i].setAttribute('challenge', url + (url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now());
      }
    }

    // The widgets mount a microtask after the element is defined, and their
    // reset() and verify() only work from then: register on the next task, so
    // a form that is already waiting (cached page) gets a working widget.
    setTimeout(function () {
      registerAdapter({ id: 'altcha', field: 'altcha', token: function (widget) { return altchaToken(widget); }, reset: reset });
    }, 0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

/**
 * LCMT Mailer — ALTCHA adapter.
 *
 * Loaded on pages with a form protected by ALTCHA (Altcha::enqueueAssets()),
 * or by form-handler.js on a cached page older than this script. Registers
 * the adapter form-handler.js asks for a proof on submit.
 */
import 'altcha';
import { registerAdapter } from './lib/captcha/registry';
import { expiresSoon } from './lib/captcha/altcha-proof';

(function () {
  'use strict';

  var TIMEOUT_MS = 30000;

  /**
   * Start the widget over and resolve with the proof of a new challenge.
   */
  function solve(widget) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { done(); reject(new Error('ALTCHA timed out')); }, TIMEOUT_MS);

      function onVerified(e) {
        done();
        resolve(e.detail && e.detail.payload);
      }

      function onStateChange(e) {
        if (e.detail && e.detail.state === 'error') {
          done();
          reject(new Error('ALTCHA failed'));
        }
      }

      function done() {
        clearTimeout(timer);
        widget.removeEventListener('verified', onVerified);
        widget.removeEventListener('statechange', onStateChange);
      }

      widget.addEventListener('verified', onVerified);
      widget.addEventListener('statechange', onStateChange);
      widget.reset();
      widget.verify();
    });
  }

  /**
   * The current proof while it stays valid, else the proof of a new challenge.
   */
  function token(widget) {
    var form = widget.closest('form');
    var input = form ? form.querySelector('input[name="altcha"]') : null;
    var payload = input ? input.value : '';

    if (payload && !expiresSoon(payload)) return Promise.resolve(payload);

    return solve(widget);
  }

  function reset(widget) {
    if (typeof widget.reset === 'function') widget.reset();
  }

  function init() {
    // Cache-bust the challenge URL to prevent stale cached responses.
    var widgets = document.querySelectorAll('altcha-widget');

    for (var i = 0; i < widgets.length; i++) {
      var url = widgets[i].getAttribute('challengeurl');
      if (url) {
        widgets[i].setAttribute('challengeurl', url + (url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now());
      }
    }

    // The widgets mount a microtask after the element is defined, and their
    // reset() and verify() only work from then: register on the next task, so
    // a form that is already waiting (cached page) gets a working widget.
    setTimeout(function () {
      registerAdapter({ id: 'altcha', field: 'altcha', token: token, reset: reset });
    }, 0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

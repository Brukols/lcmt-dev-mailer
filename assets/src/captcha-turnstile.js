/**
 * LCMT Mailer — Cloudflare Turnstile adapter.
 *
 * Loaded on pages with a form protected by Turnstile (Turnstile::enqueueAssets()),
 * or by form-handler.js on a cached page older than this script. Registers
 * the adapter form-handler.js asks for a token on submit; Cloudflare's
 * api.js is only requested once the visitor enters one of these forms.
 */
import { registerAdapter } from './lib/captcha/registry';
import { apiLoader, bindLazyRender, createTurnstileAdapter } from './lib/captcha/turnstile-widget';

(function () {
  'use strict';

  // Every Turnstile form of the page shares one api.js load.
  var adapter = createTurnstileAdapter({ loadApi: apiLoader(window) });

  function init() {
    var widgets = document.querySelectorAll('[data-lcmt-captcha="turnstile"]');

    for (var i = 0; i < widgets.length; i++) {
      var form = widgets[i].closest('form');

      // Without a form there is no focus to wait for: token() renders it.
      if (form) bindLazyRender(form, widgets[i], adapter);
    }

    // Unlike ALTCHA, nothing mounts later: the adapter renders the widget
    // itself on demand, so it can register right away.
    registerAdapter(adapter);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

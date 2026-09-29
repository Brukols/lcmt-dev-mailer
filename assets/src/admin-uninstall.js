/**
 * LCMT Mailer — asks, when the plugin is deactivated, whether deleting it
 * later should also delete its received messages.
 *
 * Expects the PHP side to localize `lcmtMailerUninstall` with:
 *   - ajaxUrl, action, nonce, plugin (basename), network, confirm (text)
 */
import { createDeactivateHandler } from './lib/uninstall-prompt';

(function () {
  'use strict';

  var cfg = window.lcmtMailerUninstall;
  if (!cfg || !window.fetch) return;

  // Capture phase: runs before any other click handler of the page.
  document.addEventListener(
    'click',
    createDeactivateHandler(cfg, {
      confirm: function (text) {
        return window.confirm(text);
      },
      fetch: function (url, init) {
        return window.fetch(url, init);
      },
    }),
    true
  );
})();

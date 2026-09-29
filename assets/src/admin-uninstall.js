/**
 * LCMT Mailer — asks whether to delete the received messages with the plugin.
 *
 * Expects the PHP side to localize `lcmtMailerUninstall` with:
 *   - ajaxUrl, action, nonce, plugin (basename), network, confirm (text)
 */
import { createDeleteHandler } from './lib/uninstall-prompt';

(function () {
  'use strict';

  var cfg = window.lcmtMailerUninstall;
  if (!cfg || !window.fetch) return;

  // Capture phase: runs before the delegated handlers of WordPress.
  document.addEventListener(
    'click',
    createDeleteHandler(cfg, {
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

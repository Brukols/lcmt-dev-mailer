/**
 * LCMT Mailer — asks, when the plugin is deactivated, whether deleting it
 * later should also delete its received messages.
 *
 * Expects the PHP side to localize `lcmtMailerUninstall` with:
 *   - ajaxUrl, action, nonce, plugin (basename), network
 * and to print the #lcmt-deactivate-dialog <dialog> in the footer.
 */
import { createDeactivateHandler } from './lib/uninstall-prompt';
import { createDeactivatePrompt } from './lib/deactivate-dialog';

(function () {
  'use strict';

  var cfg = window.lcmtMailerUninstall;
  var dialog = document.getElementById('lcmt-deactivate-dialog');

  // Without <dialog> support, the plugin deactivates as usual and keeps its data.
  if (!cfg || !dialog || !window.fetch || typeof dialog.showModal !== 'function') return;

  var prompt = createDeactivatePrompt(dialog);

  // Capture phase: runs before any other click handler of the page.
  document.addEventListener(
    'click',
    createDeactivateHandler(cfg, {
      prompt: prompt,
      fetch: function (url, init) {
        return window.fetch(url, init);
      },
    }),
    true
  );
})();

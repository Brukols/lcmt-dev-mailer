/**
 * Asks, when this plugin is deactivated from the Plugins screen, whether a
 * later deletion should also delete its received messages. WordPress only
 * shows the Delete link once the plugin is inactive, when none of its code
 * runs, so the question is asked here and the answer stored for
 * uninstall.php. Then the deactivation carries on.
 */

export function deactivateLinkSelector(plugin) {
  return 'tr[data-plugin="' + plugin + '"] .deactivate a';
}

/**
 * A capture-phase click listener. The first click on this plugin's
 * Deactivate link is held back: it asks, stores the answer, then clicks the
 * link again and lets exactly that click through. Any other click on the
 * link, while the request runs or once the page is leaving, is ignored. A
 * failed request stores nothing (the data is kept) and still deactivates.
 *
 * @param {object} cfg The localized lcmtMailerUninstall object.
 * @param {{confirm: function(string): boolean, fetch: function}} env
 */
export function createDeactivateHandler(cfg, env) {
  var selector = deactivateLinkSelector(cfg.plugin);
  var state = 'idle'; // idle → asking → releasing → left

  return function (event) {
    var target = event.target;
    var link = target && target.closest ? target.closest('a') : null;

    if (!link || !link.matches(selector)) return;

    if (state === 'releasing') {
      state = 'left';
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (state !== 'idle') return;

    state = 'asking';

    var body = new URLSearchParams({
      action: cfg.action,
      nonce: cfg.nonce,
      delete: env.confirm(cfg.confirm) ? '1' : '0',
      network: cfg.network ? '1' : '0',
    });

    Promise.resolve()
      .then(function () {
        return env.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body });
      })
      .catch(function () {
        // Nothing stored: uninstall keeps the data.
      })
      .then(function () {
        state = 'releasing';
        link.click();
      });
  };
}

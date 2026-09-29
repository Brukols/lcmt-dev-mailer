/**
 * Asks, when this plugin is deleted from the Plugins screen, whether its
 * received messages go too. uninstall.php cannot ask, so the answer is
 * stored first, then WordPress' own delete flow carries on.
 */

export function deleteLinkSelector(plugin) {
  return 'tr[data-plugin="' + plugin + '"] .delete a';
}

/**
 * A capture-phase click listener. It holds the click on this plugin's
 * Delete link back, asks, stores the answer, then clicks the link again and
 * lets that second click through to WordPress. A failed request stores
 * nothing: the data is kept, and the delete still goes on.
 *
 * @param {object} cfg The localized lcmtMailerUninstall object.
 * @param {{confirm: function(string): boolean, fetch: function}} env
 */
export function createDeleteHandler(cfg, env) {
  var selector = deleteLinkSelector(cfg.plugin);
  var letThrough = false;

  return function (event) {
    var target = event.target;
    var link = target && target.closest ? target.closest('a') : null;

    if (!link || !link.matches(selector)) return undefined;

    if (letThrough) {
      letThrough = false;
      return undefined;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    var body = new URLSearchParams({
      action: cfg.action,
      nonce: cfg.nonce,
      delete: env.confirm(cfg.confirm) ? '1' : '0',
      network: cfg.network ? '1' : '0',
    });

    return Promise.resolve()
      .then(function () {
        return env.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body });
      })
      .catch(function () {
        // Nothing stored: uninstall keeps the data.
      })
      .then(function () {
        letThrough = true;
        link.click();
      });
  };
}

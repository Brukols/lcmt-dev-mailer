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
 * Deactivate link is held back and opens the dialog. Cancel puts everything
 * back. Deactivate stores the answer, then clicks the link again and lets
 * exactly that click through. Any other click on the link, while the request
 * runs or once the page is leaving, is ignored. A failed request stores
 * nothing (the data is kept) and still deactivates.
 *
 * @param {object} cfg The localized lcmtMailerUninstall object.
 * @param {{prompt: {open: function, setBusy: function}, fetch: function}} env
 *   prompt.open({onCancel, onConfirm(checked)}) shows the dialog.
 */
export function createDeactivateHandler(cfg, env) {
  var selector = deactivateLinkSelector(cfg.plugin);
  var state = 'idle'; // idle → asking → submitting → releasing → left

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

    env.prompt.open({
      onCancel: function () {
        if (state === 'asking') state = 'idle';
      },
      onConfirm: function (checked) {
        if (state !== 'asking') return;

        state = 'submitting';
        env.prompt.setBusy(true);

        var body = new URLSearchParams({
          action: cfg.action,
          nonce: cfg.nonce,
          delete: checked ? '1' : '0',
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
      },
    });
  };
}

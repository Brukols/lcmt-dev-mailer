/**
 * LCMT Dev Mailer — Admin "Generate template" button handler.
 *
 * Expects `lcmtMailerAdmin` to be localized with:
 *   - ajaxUrl, nonce, postId
 *   - i18n.generating, i18n.generateTemplate, i18n.connectionError
 */
(function () {
  'use strict';

  var cfg = window.lcmtMailerAdmin;
  if (!cfg) return;

  var btn = document.getElementById('lcmt-generate-template');
  if (!btn) return;

  var statusDiv = document.getElementById('lcmt-generate-status');

  btn.addEventListener('click', function (e) {
    e.preventDefault();

    if (!confirm(cfg.i18n.confirmGenerate || 'Generate form template?')) return;

    btn.disabled = true;
    btn.textContent = cfg.i18n.generating;

    var body = new URLSearchParams();
    body.append('action', 'lcmt_generate_form_template');
    body.append('post_id', cfg.postId);
    body.append('nonce', cfg.nonce);

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
      .then(function (res) { return res.json(); })
      .then(function (res) {
        if (statusDiv) {
          statusDiv.innerHTML =
            '<div class="notice notice-' + (res.success ? 'success' : 'error') + '"><p>' + res.data + '</p></div>';
        }
        if (res.success) {
          // Reload to reflect the new file status
          setTimeout(function () { location.reload(); }, 1000);
        }
      })
      .catch(function () {
        if (statusDiv) {
          statusDiv.innerHTML =
            '<div class="notice notice-error"><p>' + cfg.i18n.connectionError + '</p></div>';
        }
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = cfg.i18n.generateTemplate;
      });
  });
})();

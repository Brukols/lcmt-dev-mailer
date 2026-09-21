/**
 * LCMT Dev Mailer — Admin test email sender.
 *
 * Expects the PHP side to localize `lcmtMailerAdmin` with:
 *   - ajaxUrl, nonce, postId, lang
 *   - i18n.enterEmail, i18n.invalidEmail,
 *     i18n.sending, i18n.sendTestEmail, i18n.connectionError
 */
(function () {
  'use strict';

  var cfg = window.lcmtMailerAdmin;
  if (!cfg) return;

  var btn       = document.getElementById('lcmt-send-test-email');
  var statusDiv = document.getElementById('lcmt-mail-sender-status');
  var emailIn   = document.getElementById('lcmt_test_email_recipient');

  if (!btn || !statusDiv || !emailIn) return;

  function setStatus(type, msg) {
    statusDiv.innerHTML =
      '<div class="notice notice-' + type + '"><p>' + msg + '</p></div>';
  }

  function collectPlaceholders() {
    var fields = document.querySelectorAll('.lcmt-test-field');
    var data = {};
    for (var i = 0; i < fields.length; i++) {
      var name = fields[i].getAttribute('data-field-name');
      if (name) {
        data[name] = fields[i].value;
      }
    }
    return data;
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();

    var recipient = emailIn.value.trim();

    if (!recipient) {
      setStatus('error', cfg.i18n.enterEmail);
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) {
      setStatus('error', cfg.i18n.invalidEmail);
      return;
    }

    var placeholders = collectPlaceholders();

    btn.disabled = true;
    btn.textContent = cfg.i18n.sending;
    setStatus('info', cfg.i18n.sending);

    var body = new URLSearchParams();
    body.append('action', 'lcmt_send_test_mail');
    body.append('post_id', cfg.postId);
    body.append('recipient', recipient);
    body.append('placeholders', JSON.stringify(placeholders));
    body.append('lang', cfg.lang);
    body.append('nonce', cfg.nonce);

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
      .then(function (res) { return res.json(); })
      .then(function (res) {
        setStatus(res.success ? 'success' : 'error', res.data);
      })
      .catch(function () {
        setStatus('error', cfg.i18n.connectionError);
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = cfg.i18n.sendTestEmail;
      });
  });
})();

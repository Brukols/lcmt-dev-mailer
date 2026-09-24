/**
 * LCMT Mailer — Frontend form handler.
 *
 * Auto-discovers forms with [data-lcmt-endpoint] and handles:
 *   - Collecting input values by name attribute
 *   - Client-side required validation (inputs with [required])
 *   - Fetch POST to the REST endpoint
 *   - Plugin loader overlay (.lcmt-loader)
 *   - Snackbar alerts for success/error
 *   - aria-invalid on inputs and labels for error styling
 *
 * The developer writes zero JS — just HTML inputs inside forms/{key}.php.
 */
import Snackbar from 'node-snackbar';
import 'node-snackbar/dist/snackbar.min.css';
import './form-handler.css';
import 'altcha';

(function () {
  'use strict';

  var SUBMITTING_CLASS = 'lcmt-form--submitting';

  // ── Alert ──

  var DEFAULT_COLORS = {
    error: '#e53e3e',
    success: '#38a169',
  };

  /**
   * Pick black or white text for a background, whichever contrasts more.
   *
   * Alert colors are site-configurable, so a brand color light enough to make
   * white text unreadable (WCAG 1.4.3 needs 4.5:1) is a realistic choice. This
   * applies the WCAG relative-luminance formula and keeps the text legible
   * whatever color the site picked.
   */
  function readableTextColor(hex) {
    var match = /^#?([0-9a-f]{6})$/i.exec(hex || '');

    if (!match) return '#fff';

    var value = parseInt(match[1], 16);
    var channels = [(value >> 16) & 255, (value >> 8) & 255, value & 255];

    var linear = channels.map(function (channel) {
      var c = channel / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });

    var luminance = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];

    var contrastWithWhite = 1.05 / (luminance + 0.05);
    var contrastWithBlack = (luminance + 0.05) / 0.05;

    return contrastWithWhite >= contrastWithBlack ? '#fff' : '#000';
  }

  function showAlert(message, type) {
    var front = window.lcmtMailerFront || {};
    var configured = front.colors || {};
    var configuredText = front.textColors || {};

    var background = configured[type] || DEFAULT_COLORS[type] || DEFAULT_COLORS.error;

    // An empty text color means the site left the choice to us.
    var foreground = configuredText[type] || readableTextColor(background);

    Snackbar.show({
      text: message,
      duration: 5000,
      backgroundColor: background,
      actionText: (window.env && window.env.MESSAGES && window.env.MESSAGES.dismiss) || 'OK',
      textColor: foreground,
      actionTextColor: foreground,
      pos: 'bottom-center',
      customClass: 'lcmt-snackbar',
    });
  }

  // ── Loader ──

  function showLoader(form) {
    var loader = form.querySelector('.lcmt-loader');
    if (loader) loader.classList.add('lcmt-loader--active');
  }

  function hideLoader(form) {
    var loader = form.querySelector('.lcmt-loader');
    if (loader) loader.classList.remove('lcmt-loader--active');
  }

  // ── Field errors (aria-invalid) ──

  function markFieldError(input) {
    input.setAttribute('aria-invalid', 'true');

    var label = input.form
      ? input.form.querySelector('label[for="' + input.id + '"]')
      : null;

    if (label) {
      label.setAttribute('aria-invalid', 'true');
    }

    input.addEventListener('input', function handler() {
      input.removeAttribute('aria-invalid');
      if (label) label.removeAttribute('aria-invalid');
      input.removeEventListener('input', handler);
    });
  }

  function clearErrors(form) {
    var invalids = form.querySelectorAll('[aria-invalid]');
    for (var i = 0; i < invalids.length; i++) {
      invalids[i].removeAttribute('aria-invalid');
    }
    form.classList.remove(SUBMITTING_CLASS);
  }

  // ── Form binding ──

  function init() {
    var forms = document.querySelectorAll('[data-lcmt-endpoint]');
    for (var i = 0; i < forms.length; i++) {
      bindForm(forms[i]);
    }
  }

  function bindForm(form) {
    // Cache-bust the ALTCHA challenge URL to prevent stale cached responses
    var altchaWidget = form.querySelector('altcha-widget');
    if (altchaWidget) {
      var url = altchaWidget.getAttribute('challengeurl');
      if (url) {
        altchaWidget.setAttribute('challengeurl', url + (url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now());
      }
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (form.classList.contains(SUBMITTING_CLASS)) return;

      clearErrors(form);

      var endpoint = form.getAttribute('data-lcmt-endpoint');
      var nonce = form.getAttribute('data-lcmt-nonce');

      // Collect all named inputs
      var inputs = form.querySelectorAll('[name]');
      var data = {};
      var errorFields = [];

      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var name = input.name;
        var value = (input.value || '').trim();

        if (input.required && !value) {
          markFieldError(input);
          errorFields.push(name);
          continue;
        }

        if (input.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          markFieldError(input);
          errorFields.push(name);
          continue;
        }

        data[name] = value;
      }

      if (errorFields.length > 0) {
        showAlert(window.lcmtMailerFront && window.lcmtMailerFront.i18n.requiredFields || 'Please fill in all required fields.', 'error');
        return;
      }

      // Check ALTCHA if widget is present
      var altchaWidget = form.querySelector('altcha-widget');
      if (altchaWidget) {
        var altchaInput = form.querySelector('input[name="altcha"]');
        if (!altchaInput || !altchaInput.value) {
          showAlert(window.lcmtMailerFront && window.lcmtMailerFront.i18n.captchaFailed || 'Security verification in progress. Please try again.', 'error');
          return;
        }
        data['altcha'] = altchaInput.value;
      }

      // Submit
      form.classList.add(SUBMITTING_CLASS);
      showLoader(form);

      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(data),
      })
        .then(function (res) { return res.json(); })
        .then(function (res) {
          if (res.success) {
            showAlert(res.message, 'success');
            form.reset();
          } else {
            showAlert(res.message || 'An error occurred.', 'error');

            // Mark specific fields from server errors
            if (res.errors && Array.isArray(res.errors)) {
              for (var i = 0; i < res.errors.length; i++) {
                var match = res.errors[i].match(/"([^"]+)"/);
                if (match) {
                  var input = form.querySelector('[name="' + match[1] + '"]');
                  if (input) markFieldError(input);
                }
              }
            }
          }
        })
        .catch(function () {
          showAlert(window.lcmtMailerFront && window.lcmtMailerFront.i18n.genericError || 'An error occurred.', 'error');
        })
        .finally(function () {
          form.classList.remove(SUBMITTING_CLASS);
          hideLoader(form);
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

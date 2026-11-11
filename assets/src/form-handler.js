/**
 * LCMT Mailer — Frontend form handler.
 *
 * Auto-discovers forms with [data-lcmt-endpoint] and handles:
 *   - Collecting input values by name attribute
 *   - Client-side required validation (inputs with [required])
 *   - Fetch POST to the REST endpoint
 *   - Plugin loader overlay (.lcmt-loader)
 *   - Snackbar alerts for success/error
 *   - aria-invalid on inputs, their labels and the legend of their fieldset
 *     (a whole radio group at once), see lib/field-errors.js
 *   - File fields: browser check of format and size, multipart body, the
 *     chosen file name in [data-lcmt-file-name] (see lib/file-field.js)
 *   - A bubbling `lcmt:success` event on the form, then the form's
 *     confirmation page when it has one (see lib/form-success.js)
 *
 * The developer writes zero JS — just HTML inputs inside forms/{key}.php.
 */
import Snackbar from 'node-snackbar';
import 'node-snackbar/dist/snackbar.min.css';
import './form-handler.css';
import { formContext } from './lib/attribution';
import { requestUrl } from './lib/request-url';
import { fieldValue } from './lib/field-value';
import { handleSuccess } from './lib/form-success';
import { errorGroup, clearEvent } from './lib/field-errors';
import { fileLimits, fileProblem, requestBody, showFileName } from './lib/file-field';
import { captchaToken, resetCaptcha } from './lib/captcha/registry';
import { scriptBase, adapterLoader } from './lib/captcha/script-loader';

(function () {
  'use strict';

  var SUBMITTING_CLASS = 'lcmt-form--submitting';

  // Each spam protection has its own captcha-{id}.js next to this file. A
  // page from a full-page cache may lack the one its form needs: the form
  // then loads it from here (see lib/captcha/script-loader.js).
  var loadCaptchaAdapter = adapterLoader(scriptBase(document.currentScript && document.currentScript.src), document);

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

  // The control, its labels and its fieldset's legend; a radio brings its
  // whole group. Cleared as soon as the visitor changes any control of it.
  function markFieldError(input) {
    var group = errorGroup(input, input.form);

    for (var i = 0; i < group.targets.length; i++) {
      group.targets[i].setAttribute('aria-invalid', 'true');
    }

    function handler() {
      for (var t = 0; t < group.targets.length; t++) {
        group.targets[t].removeAttribute('aria-invalid');
      }

      for (var c = 0; c < group.controls.length; c++) {
        group.controls[c].removeEventListener(clearEvent(group.controls[c]), handler);
      }
    }

    for (var j = 0; j < group.controls.length; j++) {
      group.controls[j].addEventListener(clearEvent(group.controls[j]), handler);
    }
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
    // A hidden spam protection widget can keep a required field of its own
    // (ALTCHA's checkbox) that its reset clears after each answer: left to
    // the browser, that invisible field blocks every later submit before this
    // handler runs. Required fields are checked here and every value again
    // on the server.
    form.noValidate = true;

    // Time spent filling the form, from the first field the visitor enters.
    var startedAt = null;

    form.addEventListener('focusin', function () {
      if (!startedAt) startedAt = Date.now();
    });

    // The chosen file name, in the [data-lcmt-file-name="<field>"] element
    // the form file may hold; its own text shows again without a file.
    var fileNameEls = form.querySelectorAll('[data-lcmt-file-name]');
    var emptyTexts = [];

    for (var n = 0; n < fileNameEls.length; n++) {
      emptyTexts.push(fileNameEls[n].textContent);
    }

    function refreshFileName(input) {
      for (var k = 0; k < fileNameEls.length; k++) {
        if (fileNameEls[k].getAttribute('data-lcmt-file-name') === input.name) {
          showFileName(fileNameEls[k], input.files && input.files[0], emptyTexts[k]);
        }
      }
    }

    form.addEventListener('change', function (e) {
      if (e.target && e.target.type === 'file') refreshFileName(e.target);
    });

    // reset fires before the fields are cleared.
    form.addEventListener('reset', function () {
      setTimeout(function () {
        var files = form.querySelectorAll('input[type="file"]');
        for (var k = 0; k < files.length; k++) refreshFileName(files[k]);
      }, 0);
    });

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
      var fileInputs = [];
      var limits = fileLimits(form);
      var fileError = '';

      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var name = input.name;
        // An unticked box counts as empty, see lib/field-value.js.
        var value = fieldValue(input, inputs);

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

        if (input.type === 'file') {
          var problem = fileProblem(input.files && input.files[0], limits);

          if (problem) {
            markFieldError(input);
            fileError = fileError || problem;
            continue;
          }

          // Sent as a file (when one is chosen), not as a text value.
          fileInputs.push(input);
          continue;
        }

        data[name] = value;
      }

      var front = window.lcmtMailerFront || {};
      var strings = front.i18n || {};

      if (errorFields.length > 0) {
        showAlert(strings.requiredFields || 'Please fill in all required fields.', 'error');
        return;
      }

      if (fileError) {
        var fileMessage = fileError === 'size'
          ? strings.fileTooLarge || 'This file is too large.'
          : strings.fileType || 'This file format is not accepted.';

        showAlert(limits.hint ? fileMessage + ' ' + limits.hint : fileMessage, 'error');
        return;
      }

      // Submit
      form.classList.add(SUBMITTING_CLASS);
      showLoader(form);

      var captchaFailed = false;

      // Set while the browser leaves for the confirmation page: the form
      // stays busy so it is not sent a second time on the way.
      var redirecting = false;

      captchaToken(form, window, undefined, loadCaptchaAdapter)
        .catch(function (err) {
          captchaFailed = true;
          throw err;
        })
        .then(function (captcha) {
          if (captcha) data[captcha.field] = captcha.token;
          data['_context'] = formContext(startedAt);

          // Multipart as soon as the form has a file field, even with no
          // file chosen; JSON otherwise.
          var request = requestBody(data, fileInputs, window.FormData);

          request.headers['X-WP-Nonce'] = nonce;

          return fetch(requestUrl(endpoint, window.lcmtMailerFront), {
            method: 'POST',
            credentials: 'same-origin',
            headers: request.headers,
            body: request.body,
          });
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
          resetCaptcha(form, window);

          if (res.success) {
            // lcmt:success listeners still see the values the visitor sent.
            handleSuccess(form, res, {
              redirect: function (url) {
                redirecting = true;
                form.reset();
                window.location.assign(url);
              },
              showMessage: function () {
                showAlert(res.message, 'success');
                form.reset();
              },
            });
            startedAt = null;
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
          var i18n = (window.lcmtMailerFront && window.lcmtMailerFront.i18n) || {};

          resetCaptcha(form, window);
          showAlert(captchaFailed
            ? i18n.captchaFailed || 'Security verification failed. Please try again.'
            : i18n.genericError || 'An error occurred.', 'error');
        })
        .finally(function () {
          if (redirecting) return;

          form.classList.remove(SUBMITTING_CLASS);
          hideLoader(form);
        });
    });

    // Back from the confirmation page, the browser may restore this page as
    // it was left (back/forward cache), still busy: free the form.
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;

      form.classList.remove(SUBMITTING_CLASS);
      hideLoader(form);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

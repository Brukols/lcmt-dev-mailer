/**
 * LCMT Mailer — Copy buttons of the Privacy policy section of the Data
 * retention page.
 */
import { initCopyButtons } from './lib/copy-button';

(function () {
  'use strict';

  var root = document.querySelector('.lcmt-privacy-docs');
  if (!root) return;

  initCopyButtons(root, {
    clipboard: navigator.clipboard,
    fallback: function (text) {
      var area = document.createElement('textarea');

      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();

      try {
        return document.execCommand('copy');
      } finally {
        document.body.removeChild(area);
      }
    },
    setTimeout: function (fn, ms) {
      return window.setTimeout(fn, ms);
    },
    clearTimeout: function (id) {
      window.clearTimeout(id);
    },
  });
})();

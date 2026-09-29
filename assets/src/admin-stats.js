/**
 * LCMT Mailer — Chart | Table toggle of the Statistics page.
 */
import { initStats } from './lib/stats-view';

(function () {
  'use strict';

  var root = document.querySelector('.lcmt-stats');
  if (!root) return;

  var storage = null;
  try {
    storage = window.localStorage;
  } catch (e) {
    // Blocked storage: the toggle still works, it just forgets.
  }

  initStats(root, storage);
})();

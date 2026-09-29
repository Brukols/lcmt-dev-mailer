/**
 * LCMT Mailer — remembers the landing page of the visit for form statistics.
 */
import { currentTouch, startsVisit, storedTouch, storeTouch, mayStore } from './lib/attribution';

(function () {
  'use strict';

  var touch = currentTouch();

  if (storedTouch() && !startsVisit(touch)) return;

  if (mayStore()) {
    storeTouch(touch);
    return;
  }

  // Keep the landing in memory until the visitor accepts statistics on
  // this page, which is where most consent banners are answered.
  document.addEventListener('wp_listen_for_consent_change', function (e) {
    if (e.detail && e.detail.statistics === 'allow') storeTouch(touch);
  });
})();

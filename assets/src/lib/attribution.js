/**
 * Where a visit came from, remembered across the pages of a session so a
 * form sent three pages later still knows the ad or search that brought
 * the visitor.
 *
 * Only paths, the referrer's origin and campaign parameters are kept, in
 * sessionStorage (gone when the tab closes). Click ids are kept by name,
 * never their value.
 */
var STORAGE_KEY = 'lcmtMailerLanding';
var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign'];
var CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id'];

/**
 * Scheme and host of the referrer. Its path and query string can carry a
 * click id or other personal data, and are never kept.
 */
function referrerOrigin(referrer) {
  try {
    var url = new URL(referrer);
    return url.protocol === 'http:' || url.protocol === 'https:' ? url.origin : '';
  } catch (err) {
    return '';
  }
}

export function currentTouch() {
  var params = new URLSearchParams(window.location.search);
  var touch = { path: window.location.pathname, referrer: referrerOrigin(document.referrer || '') };

  UTM_KEYS.forEach(function (key) {
    var value = params.get(key);
    if (value) touch[key] = value.slice(0, 150);
  });

  for (var i = 0; i < CLICK_IDS.length; i++) {
    if (params.has(CLICK_IDS[i])) {
      touch.click_id = CLICK_IDS[i];
      break;
    }
  }

  return touch;
}

/**
 * A touch that starts a new visit: campaign parameters, or a link from
 * another site. It replaces the landing already remembered.
 */
export function startsVisit(touch) {
  if (touch.click_id || touch.utm_source) return true;
  if (!touch.referrer) return false;

  try {
    return new URL(touch.referrer).host !== window.location.host;
  } catch (err) {
    return false;
  }
}

export function storedTouch() {
  try {
    var raw = window.sessionStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (err) {
    return null;
  }
}

export function storeTouch(touch) {
  try {
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(touch));
  } catch (err) {
    // Storage blocked or full: the form falls back to the current page.
  }
}

/**
 * Whether the landing may be written to the browser: the statistics consent
 * when WP Consent API is installed, the site setting otherwise.
 *
 * When the server says WP Consent API is active but its script has not run
 * yet, the answer is "not yet": the site setting only applies to sites
 * without a consent tool.
 */
export function mayStore() {
  if (typeof window.wp_has_consent === 'function') {
    return window.wp_has_consent('statistics');
  }

  var config = window.lcmtMailerAttribution || {};
  if (config.consentApi) return false;

  return !!config.storeWithoutConsent;
}

export function device() {
  var width = window.innerWidth || document.documentElement.clientWidth;
  var coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

  if (width < 768) return 'mobile';
  if (coarse && width < 1280) return 'tablet';
  return 'desktop';
}

/**
 * The context sent with a form. Without a remembered landing (no consent,
 * storage blocked, form on the landing page), the current page stands in.
 *
 * @param {number|null} startedAt Time of the first interaction with the form.
 */
export function formContext(startedAt) {
  var current = currentTouch();
  var landing = storedTouch();

  if (!landing || startsVisit(current)) landing = current;

  return {
    page: current.path,
    landing: landing,
    device: device(),
    locale: navigator.language || '',
    seconds: startedAt ? Math.round((Date.now() - startedAt) / 1000) : null,
  };
}

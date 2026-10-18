/**
 * The URL a form posts to: its REST endpoint, plus the page language as
 * `lang` on a multilingual site, so the answer and the mail use it.
 *
 * @param {string} endpoint The form's data-lcmt-endpoint.
 * @param {{lang?: string}|undefined} front The localized lcmtMailerFront data.
 * @returns {string}
 */
export function requestUrl(endpoint, front) {
  var lang = front && front.lang;

  if (!lang) return endpoint;

  return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'lang=' + encodeURIComponent(lang);
}

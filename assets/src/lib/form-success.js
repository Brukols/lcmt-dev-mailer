/**
 * What a form does after a successful answer: announce it with a bubbling
 * `lcmt:success` event, then either go to the form's confirmation page (the
 * `redirect` URL of the answer) or show the success message as before.
 */

export var SUCCESS_EVENT = 'lcmt:success';

/**
 * The confirmation page URL of an answer, or '' when there is none or it is
 * not an http(s) URL or a path of this site. The server only sends URLs of the
 * site; this keeps `javascript:` and the like out of location.assign anyway.
 *
 * @param {{redirect?: unknown}|null|undefined} res
 * @returns {string}
 */
export function redirectUrl(res) {
  var url = res && typeof res.redirect === 'string' ? res.redirect.trim() : '';

  return /^(https?:\/\/|\/(?!\/))/i.test(url) ? url : '';
}

/**
 * Dispatch `lcmt:success` on the form (bubbling, cancelable, the answer as
 * `detail`), then redirect, unless a listener called preventDefault(), or show
 * the message.
 *
 * @param {EventTarget} form
 * @param {object} res The JSON answer of the endpoint.
 * @param {{redirect: function(string): void, showMessage: function(): void}} actions
 * @returns {'redirect'|'message'}
 */
export function handleSuccess(form, res, actions) {
  var url = redirectUrl(res);
  var proceed = form.dispatchEvent(new CustomEvent(SUCCESS_EVENT, {
    bubbles: true,
    cancelable: true,
    detail: res,
  }));

  if (url && proceed) {
    actions.redirect(url);
    return 'redirect';
  }

  actions.showMessage();
  return 'message';
}

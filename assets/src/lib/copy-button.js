/**
 * Copy buttons of the Privacy policy section: a button carrying its text in
 * data-lcmt-copy and its confirmation in data-copied. Copying goes through the
 * async Clipboard API, then through a fallback (a hidden textarea and
 * execCommand) for browsers or pages where that API is not available.
 */

export var RESET_DELAY = 1500;

/**
 * @param {string} text
 * @param {{clipboard?: {writeText: function}, fallback: function}} env
 *   fallback(text) copies synchronously and returns whether it worked.
 * @returns {Promise<boolean>}
 */
export function copyText(text, env) {
  var viaFallback = function () {
    try {
      return Boolean(env.fallback(text));
    } catch (e) {
      return false;
    }
  };

  if (!env.clipboard || typeof env.clipboard.writeText !== 'function') {
    return Promise.resolve(viaFallback());
  }

  return Promise.resolve()
    .then(function () {
      return env.clipboard.writeText(text);
    })
    .then(
      function () {
        return true;
      },
      function () {
        return viaFallback();
      }
    );
}

/**
 * Wire every [data-lcmt-copy] button under root. On success the button reads
 * its data-copied text for a moment and the polite live region (the
 * [data-lcmt-copy-status] element) announces it. A failed copy changes nothing.
 *
 * @param {{querySelectorAll: function, querySelector: function}} root
 * @param {{clipboard?: object, fallback: function, setTimeout: function}} env
 */
export function initCopyButtons(root, env) {
  var status = root.querySelector('[data-lcmt-copy-status]');

  Array.prototype.forEach.call(root.querySelectorAll('[data-lcmt-copy]'), function (button) {
    var label = button.textContent;
    var timer = null;

    button.addEventListener('click', function () {
      copyText(button.getAttribute('data-lcmt-copy') || '', env).then(function (done) {
        if (!done) return;

        var copied = button.getAttribute('data-copied') || label;

        button.textContent = copied;
        if (status) status.textContent = copied;

        if (timer !== null) env.clearTimeout(timer);
        timer = env.setTimeout(function () {
          timer = null;
          button.textContent = label;
          if (status) status.textContent = '';
        }, RESET_DELAY);
      });
    });
  });
}

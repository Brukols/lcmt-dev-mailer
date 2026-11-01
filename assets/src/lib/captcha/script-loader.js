/**
 * Load a spam protection's adapter script on demand.
 *
 * The plugin enqueues captcha-{id}.js next to every form that needs it, but
 * a full-page cache can serve HTML rendered before that script existed. The
 * form handler then fetches it itself, from its own dist folder.
 */

/**
 * The folder of a script URL, with a trailing slash and no query string.
 */
export function scriptBase(src) {
  if (!src) return '';

  return String(src).split(/[?#]/)[0].replace(/[^/]*$/, '');
}

/**
 * A loadAdapter(id) callback that appends {base}captcha-{id}.js once per id.
 */
export function adapterLoader(base, doc = document) {
  var loaded = {};

  return function (id) {
    // Ids come from the page markup: keep them to a file name.
    if (!base || !/^[a-z0-9-]+$/.test(id) || loaded[id]) return;

    loaded[id] = true;

    var file = 'captcha-' + id + '.js';

    // Enqueued by the page but not run yet (it is deferred): it registers
    // its adapter soon, and a second copy would define everything twice.
    if (doc.querySelector('script[src*="/' + file + '"]')) return;

    var script = doc.createElement('script');
    script.src = base + file;
    script.defer = true;
    (doc.head || doc.body).appendChild(script);
  };
}

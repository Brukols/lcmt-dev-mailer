/**
 * Minimal browser stand-ins for the attribution tests, set on globalThis so
 * the modules run unchanged under node. No DOM package needed.
 */
export function setEnv(options) {
  var opts = Object.assign(
    {
      url: 'https://site.test/',
      referrer: '',
      width: 1440,
      coarse: false,
      language: 'fr-FR',
      storage: {},
      storageThrows: false,
      setItemThrows: false,
    },
    options || {}
  );
  var url = new URL(opts.url);
  var listeners = {};

  var sessionStorage = {
    data: Object.assign({}, opts.storage),
    getItem: function (key) {
      if (opts.storageThrows) throw new Error('blocked');
      return Object.prototype.hasOwnProperty.call(this.data, key) ? this.data[key] : null;
    },
    setItem: function (key, value) {
      if (opts.setItemThrows) throw new Error('full');
      this.data[key] = String(value);
    },
  };

  globalThis.window = {
    location: { search: url.search, pathname: url.pathname, host: url.host },
    sessionStorage: sessionStorage,
    innerWidth: opts.width,
    matchMedia: function () {
      return { matches: opts.coarse };
    },
  };
  globalThis.document = {
    referrer: opts.referrer,
    documentElement: { clientWidth: opts.width },
    addEventListener: function (type, fn) {
      (listeners[type] = listeners[type] || []).push(fn);
    },
    dispatch: function (type, detail) {
      (listeners[type] || []).forEach(function (fn) {
        fn({ type: type, detail: detail });
      });
    },
  };
  Object.defineProperty(globalThis, 'navigator', {
    value: { language: opts.language },
    configurable: true,
    writable: true,
  });

  return { storage: sessionStorage, document: globalThis.document };
}

/**
 * File fields of a form: the browser-side check of the chosen file against
 * the limits the server printed on the <form> (the server checks again), the
 * request body (multipart when the form has a file field, JSON otherwise),
 * and the element showing the chosen file name.
 */

/**
 * @param {string} name
 * @returns {string} The last extension, lower case, '' without one.
 */
export function fileExtension(name) {
  var dot = (name || '').lastIndexOf('.');

  return dot === -1 || dot === name.length - 1 ? '' : name.slice(dot + 1).toLowerCase();
}

/**
 * The limits printed by the server: data-lcmt-file-extensions (comma list),
 * data-lcmt-file-max-size (bytes), data-lcmt-file-hint (translated line).
 *
 * @param {{getAttribute: function(string): ?string}} form
 * @returns {{extensions: string[], maxSize: number, hint: string}}
 */
export function fileLimits(form) {
  var extensions = (form.getAttribute('data-lcmt-file-extensions') || '')
    .split(',')
    .map(function (e) { return e.trim().toLowerCase(); })
    .filter(Boolean);

  var maxSize = parseInt(form.getAttribute('data-lcmt-file-max-size') || '', 10);

  return {
    extensions: extensions,
    maxSize: maxSize > 0 ? maxSize : 0,
    hint: form.getAttribute('data-lcmt-file-hint') || '',
  };
}

/**
 * @param {?{name: string, size: number}} file The chosen file, if any.
 * @param {{extensions: string[], maxSize: number}} limits
 * @returns {''|'type'|'size'}
 */
export function fileProblem(file, limits) {
  if (!file) return '';

  if (limits.extensions.length && limits.extensions.indexOf(fileExtension(file.name)) === -1) {
    return 'type';
  }

  if (limits.maxSize && file.size > limits.maxSize) {
    return 'size';
  }

  return '';
}

/**
 * The body and headers of the request. Without a file field, JSON exactly as
 * before; with one, multipart: objects (the `_context`) as JSON strings, and
 * the chosen file of each file field. No Content-Type for multipart: the
 * browser adds it with the boundary.
 *
 * @param {Object<string, *>} data
 * @param {Array<{name: string, files: ?ArrayLike<{name: string}>}>} fileInputs
 * @param {function(new:FormData)} FormDataCtor
 * @returns {{body: (string|FormData), headers: Object<string, string>}}
 */
export function requestBody(data, fileInputs, FormDataCtor) {
  if (!fileInputs.length) {
    return { body: JSON.stringify(data), headers: { 'Content-Type': 'application/json' } };
  }

  var body = new FormDataCtor();

  Object.keys(data).forEach(function (key) {
    var value = data[key];
    body.append(key, value !== null && typeof value === 'object' ? JSON.stringify(value) : String(value));
  });

  fileInputs.forEach(function (input) {
    var file = input.files && input.files[0];
    if (file) body.append(input.name, file, file.name);
  });

  return { body: body, headers: {} };
}

/**
 * Show the chosen file name in a [data-lcmt-file-name] element, flagged with
 * data-lcmt-file-chosen; without a file, its original text comes back.
 *
 * @param {?{textContent: string, setAttribute: Function, removeAttribute: Function}} el
 * @param {?{name: string}} file
 * @param {string} emptyText
 */
export function showFileName(el, file, emptyText) {
  if (!el) return;

  if (file) {
    el.textContent = file.name;
    el.setAttribute('data-lcmt-file-chosen', '');
  } else {
    el.textContent = emptyText;
    el.removeAttribute('data-lcmt-file-chosen');
  }
}

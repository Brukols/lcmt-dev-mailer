/**
 * File fields of a form: the browser-side check of the chosen file against
 * the limits the server printed on the <form> (the server checks again), the
 * request body (multipart when the form has a file field, JSON otherwise),
 * and the element showing the chosen file name. A field taking several files
 * (<input type="file" name="x[]" multiple>) keeps its own list of files, see
 * fileListController().
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
 * the chosen file of each file field, every kept file of a field taking
 * several (`multiple`: its files all go under its name, `x[]`). No
 * Content-Type for multipart: the browser adds it with the boundary.
 *
 * @param {Object<string, *>} data
 * @param {Array<{name: string, files: ?ArrayLike<{name: string}>, multiple: (boolean|undefined)}>} fileInputs
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
    if (input.multiple) {
      Array.prototype.forEach.call(input.files || [], function (each) {
        body.append(input.name, each, each.name);
      });
      return;
    }

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

// ── Several files ([name files]) ──

/**
 * The number of files a [name files] field takes, from
 * data-lcmt-file-max-files; 0 (no cap in the browser) without it.
 *
 * @param {{getAttribute: function(string): ?string}} form
 * @returns {number}
 */
export function fileMaxFiles(form) {
  var max = parseInt(form.getAttribute('data-lcmt-file-max-files') || '', 10);

  return max > 0 ? max : 0;
}

/**
 * The field name of an input sent as `name[]`.
 *
 * @param {string} name
 * @returns {string}
 */
export function fieldBaseName(name) {
  return (name || '').replace(/\[\]$/, '');
}

/**
 * The kept files plus newly chosen ones: a file already kept (same name and
 * size) is ignored, and no more than maxFiles are kept (0: no cap).
 *
 * @param {Array<{name: string, size: number}>} kept
 * @param {ArrayLike<{name: string, size: number}>} chosen
 * @param {number} maxFiles
 * @returns {{files: Array, dropped: number}} dropped: new files left out by the cap.
 */
export function addFiles(kept, chosen, maxFiles) {
  var files = kept.slice();
  var dropped = 0;

  Array.prototype.forEach.call(chosen || [], function (file) {
    var known = files.some(function (k) { return k.name === file.name && k.size === file.size; });

    if (known) return;

    if (maxFiles && files.length >= maxFiles) {
      dropped++;
      return;
    }

    files.push(file);
  });

  return { files: files, dropped: dropped };
}

/**
 * What is wrong with the files of a [name files] field: '' , 'count' (more
 * than maxFiles), or the first fileProblem() of one of them.
 *
 * @param {Array<{name: string, size: number}>} files
 * @param {{extensions: string[], maxSize: number}} limits
 * @param {number} maxFiles
 * @returns {''|'count'|'type'|'size'}
 */
export function filesProblem(files, limits, maxFiles) {
  if (maxFiles && files.length > maxFiles) return 'count';

  for (var i = 0; i < files.length; i++) {
    var problem = fileProblem(files[i], limits);
    if (problem) return problem;
  }

  return '';
}

/**
 * Show the kept files in the [data-lcmt-file-list] element: one <li> per
 * file, its name and a remove button (data-lcmt-file-remove holds its
 * index); the element gets data-lcmt-file-chosen while the list is not empty.
 *
 * @param {?Object} list
 * @param {Array<{name: string}>} files
 * @param {{createElement: function(string): Object}} doc
 * @param {function(string): string} removeLabel The accessible label of a remove button, from the file name.
 */
export function renderFileList(list, files, doc, removeLabel) {
  if (!list) return;

  while (list.firstChild) list.removeChild(list.firstChild);

  files.forEach(function (file, index) {
    var item = doc.createElement('li');
    var name = doc.createElement('span');
    var remove = doc.createElement('button');

    name.textContent = file.name;
    remove.setAttribute('type', 'button');
    remove.setAttribute('data-lcmt-file-remove', String(index));
    remove.setAttribute('aria-label', removeLabel(file.name));
    remove.textContent = '\u00d7';

    item.appendChild(name);
    item.appendChild(remove);
    list.appendChild(item);
  });

  if (files.length) {
    list.setAttribute('data-lcmt-file-chosen', '');
  } else {
    list.removeAttribute('data-lcmt-file-chosen');
  }
}

/**
 * The list of files of one [name files] input. choose() (on `change`) adds
 * the input's chosen files to the list, then empties the input so the list
 * alone is sent and the same file can be chosen again after a removal; it
 * returns how many files the cap left out. A click on a remove button of
 * the list element takes its file off and moves the focus to the next
 * button, or back to the input.
 *
 * @param {{files: ?ArrayLike, value: string, focus: Function}} input
 * @param {?Object} list The [data-lcmt-file-list] element, if any.
 * @param {{maxFiles: number, document: Object, removeLabel: function(string): string}} options
 */
export function fileListController(input, list, options) {
  var files = [];

  function render() {
    renderFileList(list, files, options.document, options.removeLabel);
  }

  if (list) {
    list.addEventListener('click', function (e) {
      var button = e.target && e.target.closest ? e.target.closest('[data-lcmt-file-remove]') : null;
      if (!button) return;

      var index = parseInt(button.getAttribute('data-lcmt-file-remove'), 10);
      if (!(index >= 0 && index < files.length)) return;

      files.splice(index, 1);
      render();

      var buttons = list.querySelectorAll('[data-lcmt-file-remove]');

      if (buttons.length) {
        buttons[Math.min(index, buttons.length - 1)].focus();
      } else {
        input.focus();
      }
    });
  }

  return {
    files: function () { return files.slice(); },
    choose: function () {
      var result = addFiles(files, input.files || [], options.maxFiles);

      files = result.files;

      try {
        input.value = '';
      } catch (err) {
        // Old browsers may refuse it: the list is what is sent anyway.
      }

      render();

      return result.dropped;
    },
    clear: function () {
      files = [];
      render();
    },
  };
}

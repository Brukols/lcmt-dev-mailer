import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  fileExtension, fileLimits, fileProblem, requestBody, showFileName,
  fileMaxFiles, fieldBaseName, addFiles, filesProblem, renderFileList, fileListController,
} from '../../assets/src/lib/file-field.js';

/** A stand-in for an element: attributes and text only. */
function element(attrs, text) {
  var a = Object.assign({}, attrs);
  return {
    textContent: text || '',
    getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(a, n) ? a[n] : null; },
    setAttribute: function (n, v) { a[n] = String(v); },
    removeAttribute: function (n) { delete a[n]; },
    hasAttribute: function (n) { return Object.prototype.hasOwnProperty.call(a, n); },
  };
}

/** A stand-in for FormData that records what is appended. */
function FakeFormData() {
  this.entries = [];
}
FakeFormData.prototype.append = function (name, value, filename) {
  this.entries.push(filename === undefined ? [name, value] : [name, value, filename]);
};

test('reads the extension of a file name in lower case', () => {
  assert.equal(fileExtension('Plan.Final.PDF'), 'pdf');
  assert.equal(fileExtension('README'), '');
  assert.equal(fileExtension('name.'), '');
  assert.equal(fileExtension(''), '');
});

test('reads the limits the server put on the form', () => {
  var form = element({
    'data-lcmt-file-extensions': 'jpg,jpeg, PNG,,pdf',
    'data-lcmt-file-max-size': '10485760',
    'data-lcmt-file-hint': 'Formats : JPG, PNG, PDF — 10 Mo maximum',
  });

  assert.deepEqual(fileLimits(form), {
    extensions: ['jpg', 'jpeg', 'png', 'pdf'],
    maxSize: 10485760,
    hint: 'Formats : JPG, PNG, PDF — 10 Mo maximum',
  });
});

test('a form without limits checks nothing', () => {
  assert.deepEqual(fileLimits(element({})), { extensions: [], maxSize: 0, hint: '' });
  assert.deepEqual(fileLimits(element({ 'data-lcmt-file-max-size': 'abc' })).maxSize, 0);
});

test('a file of an allowed format and size passes', () => {
  var limits = { extensions: ['jpg', 'pdf'], maxSize: 1000 };

  assert.equal(fileProblem({ name: 'plan.PDF', size: 1000 }, limits), '');
  assert.equal(fileProblem(null, limits), '');
  assert.equal(fileProblem(undefined, limits), '');
});

test('a file of another format is refused', () => {
  var limits = { extensions: ['jpg', 'pdf'], maxSize: 1000 };

  assert.equal(fileProblem({ name: 'logo.png', size: 10 }, limits), 'type');
  assert.equal(fileProblem({ name: 'noextension', size: 10 }, limits), 'type');
  assert.equal(fileProblem({ name: 'plan.pdf.exe', size: 10 }, limits), 'type');
});

test('a file over the size is refused', () => {
  assert.equal(fileProblem({ name: 'plan.pdf', size: 1001 }, { extensions: ['pdf'], maxSize: 1000 }), 'size');
});

test('without limits on the form, any file passes the browser check', () => {
  assert.equal(fileProblem({ name: 'logo.png', size: 1e9 }, { extensions: [], maxSize: 0 }), '');
});

test('a form without file field sends JSON, as before', () => {
  var req = requestBody({ name: 'Jeanne', _context: { page: '/' } }, [], FakeFormData);

  assert.equal(req.body, '{"name":"Jeanne","_context":{"page":"/"}}');
  assert.deepEqual(req.headers, { 'Content-Type': 'application/json' });
});

test('a form with a file field sends multipart, the context as a JSON string', () => {
  var file = { name: 'plan.pdf', size: 3 };
  var req = requestBody(
    { name: 'Jeanne', altcha: 'token', _context: { page: '/devis' } },
    [{ name: 'brief', files: [file] }, { name: 'logo', files: [] }],
    FakeFormData
  );

  assert.ok(req.body instanceof FakeFormData);
  assert.deepEqual(req.headers, {}, 'the browser sets the multipart boundary itself');
  assert.deepEqual(req.body.entries, [
    ['name', 'Jeanne'],
    ['altcha', 'token'],
    ['_context', '{"page":"/devis"}'],
    ['brief', file, 'plan.pdf'],
  ]);
});

test('shows the chosen file name, then the original text once cleared', () => {
  var label = element({}, 'Aucun fichier');

  showFileName(label, { name: 'plan.pdf' }, 'Aucun fichier');
  assert.equal(label.textContent, 'plan.pdf');
  assert.ok(label.hasAttribute('data-lcmt-file-chosen'));

  showFileName(label, null, 'Aucun fichier');
  assert.equal(label.textContent, 'Aucun fichier');
  assert.ok(!label.hasAttribute('data-lcmt-file-chosen'));
});

test('a missing file name element is ignored', () => {
  assert.doesNotThrow(() => showFileName(null, { name: 'a.pdf' }, ''));
});

// ── Several files ([name files]) ──

/** A stand-in for a DOM node: attributes, text, children, listeners. */
function node(tag) {
  var n = element({}, '');
  n.tagName = tag.toUpperCase();
  n.children = [];
  n.listeners = {};
  n.focused = false;
  Object.defineProperty(n, 'firstChild', { get: function () { return n.children[0] || null; } });
  n.appendChild = function (c) { c.parentNode = n; n.children.push(c); return c; };
  n.removeChild = function (c) { n.children.splice(n.children.indexOf(c), 1); c.parentNode = null; return c; };
  n.addEventListener = function (type, fn) { (n.listeners[type] = n.listeners[type] || []).push(fn); };
  n.focus = function () { n.focused = true; };
  n.querySelectorAll = function (selector) {
    var attr = selector.replace(/^\[|\]$/g, '');
    var found = [];
    (function walk(el) {
      el.children.forEach(function (c) {
        if (c.hasAttribute(attr)) found.push(c);
        walk(c);
      });
    })(n);
    return found;
  };
  n.closest = function (selector) {
    var attr = selector.replace(/^\[|\]$/g, '');
    for (var el = n; el; el = el.parentNode) if (el.hasAttribute && el.hasAttribute(attr)) return el;
    return null;
  };
  return n;
}

var fakeDocument = { createElement: node };

function texts(list) {
  return list.children.map(function (li) { return li.children[0].textContent; });
}

function click(list, button) {
  list.listeners.click.forEach(function (fn) { fn({ target: button }); });
}

var pdf = { name: 'plan.pdf', size: 10 };
var png = { name: 'logo.png', size: 20 };
var jpg = { name: 'photo.jpg', size: 30 };

test('reads the maximum number of files the server put on the form', () => {
  assert.equal(fileMaxFiles(element({ 'data-lcmt-file-max-files': '3' })), 3);
  assert.equal(fileMaxFiles(element({})), 0);
  assert.equal(fileMaxFiles(element({ 'data-lcmt-file-max-files': 'x' })), 0);
});

test('the field name of an input sent as name[] has no brackets', () => {
  assert.equal(fieldBaseName('attachment[]'), 'attachment');
  assert.equal(fieldBaseName('brief'), 'brief');
});

test('chosen files add up, without duplicates, up to the maximum', () => {
  var first = addFiles([], [pdf, png], 3);
  assert.deepEqual(first, { files: [pdf, png], dropped: 0 });

  var again = addFiles(first.files, [{ name: 'plan.pdf', size: 10 }, jpg, { name: 'more.pdf', size: 1 }], 3);
  assert.deepEqual(again.files, [pdf, png, jpg]);
  assert.equal(again.dropped, 1, 'the duplicate is not counted as dropped');

  assert.deepEqual(addFiles([], [pdf, png], 0).files, [pdf, png], 'no maximum on the form: no cap');
  assert.deepEqual(addFiles([pdf], [{ name: 'plan.pdf', size: 11 }], 3).files.length, 2, 'same name, other size: another file');
});

test('the kept files are checked one by one, and their number', () => {
  var limits = { extensions: ['pdf', 'png'], maxSize: 25 };

  assert.equal(filesProblem([pdf, png], limits, 3), '');
  assert.equal(filesProblem([], limits, 3), '');
  assert.equal(filesProblem([pdf, jpg], limits, 3), 'type');
  assert.equal(filesProblem([pdf, { name: 'big.png', size: 26 }], limits, 3), 'size');
  assert.equal(filesProblem([pdf, png, pdf], limits, 2), 'count');
});

test('a field taking several files sends each kept file under name[]', () => {
  var req = requestBody(
    { name: 'Jeanne' },
    [{ name: 'attachment[]', files: [pdf, png], multiple: true }, { name: 'brief', files: [jpg] }],
    FakeFormData
  );

  assert.deepEqual(req.body.entries, [
    ['name', 'Jeanne'],
    ['attachment[]', pdf, 'plan.pdf'],
    ['attachment[]', png, 'logo.png'],
    ['brief', jpg, 'photo.jpg'],
  ]);
});

test('a field taking several files with none kept sends no file', () => {
  var req = requestBody({}, [{ name: 'attachment[]', files: [], multiple: true }], FakeFormData);

  assert.deepEqual(req.body.entries, []);
});

test('renders one item per file with a labelled remove button', () => {
  var list = node('ul');

  renderFileList(list, [pdf, png], fakeDocument, function (name) { return 'Retirer ' + name; });

  assert.deepEqual(texts(list), ['plan.pdf', 'logo.png']);
  var button = list.children[1].children[1];
  assert.equal(list.children[1].tagName, 'LI');
  assert.equal(button.tagName, 'BUTTON');
  assert.equal(button.getAttribute('type'), 'button');
  assert.equal(button.getAttribute('data-lcmt-file-remove'), '1');
  assert.equal(button.getAttribute('aria-label'), 'Retirer logo.png');
  assert.ok(list.hasAttribute('data-lcmt-file-chosen'));

  renderFileList(list, [], fakeDocument, function (name) { return name; });
  assert.equal(list.children.length, 0);
  assert.ok(!list.hasAttribute('data-lcmt-file-chosen'));
});

test('without a list element, nothing is rendered', () => {
  assert.doesNotThrow(() => renderFileList(null, [pdf], fakeDocument, String));
});

test('the controller keeps the files of several selections and clears the input', () => {
  var input = { files: [pdf], value: 'C:\\fakepath\\plan.pdf', focus: function () {} };
  var list = node('ul');
  var files = fileListController(input, list, { maxFiles: 2, document: fakeDocument, removeLabel: String });

  assert.equal(files.choose(), 0);
  assert.equal(input.value, '', 'the same file can be chosen again after a removal');

  input.files = [png, jpg];
  assert.equal(files.choose(), 1, 'one file over the maximum');
  assert.deepEqual(files.files(), [pdf, png]);
  assert.deepEqual(texts(list), ['plan.pdf', 'logo.png']);
});

test('the remove button takes its file off the list and moves the focus', () => {
  var input = { files: [pdf, png, jpg], value: '', focused: false, focus: function () { this.focused = true; } };
  var list = node('ul');
  var files = fileListController(input, list, { maxFiles: 3, document: fakeDocument, removeLabel: String });

  files.choose();
  click(list, list.children[0].children[1]);

  assert.deepEqual(files.files(), [png, jpg]);
  assert.deepEqual(texts(list), ['logo.png', 'photo.jpg']);
  assert.ok(list.children[0].children[1].focused, 'focus on the next remove button');

  click(list, list.children[1].children[1]);
  click(list, list.children[0].children[1]);
  assert.deepEqual(files.files(), []);
  assert.ok(input.focused, 'focus back on the input once the list is empty');
});

test('a click elsewhere in the list changes nothing', () => {
  var input = { files: [pdf], value: '', focus: function () {} };
  var list = node('ul');
  var files = fileListController(input, list, { maxFiles: 3, document: fakeDocument, removeLabel: String });

  files.choose();
  click(list, list.children[0].children[0]);

  assert.deepEqual(files.files(), [pdf]);
});

test('clearing the controller empties the list', () => {
  var input = { files: [pdf, png], value: '', focus: function () {} };
  var list = node('ul');
  var files = fileListController(input, list, { maxFiles: 3, document: fakeDocument, removeLabel: String });

  files.choose();
  files.clear();

  assert.deepEqual(files.files(), []);
  assert.equal(list.children.length, 0);
  assert.ok(!list.hasAttribute('data-lcmt-file-chosen'));
});

test('the controller works without a list element', () => {
  var input = { files: [pdf], value: '', focus: function () {} };
  var files = fileListController(input, null, { maxFiles: 3, document: fakeDocument, removeLabel: String });

  assert.equal(files.choose(), 0);
  assert.deepEqual(files.files(), [pdf]);
});

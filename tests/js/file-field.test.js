import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fileExtension, fileLimits, fileProblem, requestBody, showFileName } from '../../assets/src/lib/file-field.js';

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

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseFields, fileSettings, applyFileSettings } from '../../assets/src/lib/file-settings.js';

test('parses placeholders with the rules of FieldParser::parse()', () => {
  assert.deepEqual(parseFields(['[name*] [email* email]', '[brief file] [Photos FILES] [phone phone] [x unknown]']), {
    name: { name: 'name', required: true, type: 'text' },
    email: { name: 'email', required: true, type: 'email' },
    brief: { name: 'brief', required: false, type: 'file' },
    Photos: { name: 'Photos', required: false, type: 'files' },
    phone: { name: 'phone', required: false, type: 'tel' },
    x: { name: 'x', required: false, type: 'text' },
  });
});

test('duplicates: required wins, an explicit type wins over text, the first explicit type stays', () => {
  var fields = parseFields(['[doc]', '[doc* files] [doc file]']);

  assert.deepEqual(fields.doc, { name: 'doc', required: true, type: 'files' });
  assert.equal(parseFields(['[a file] [a files]']).a.type, 'file');
});

test('a malformed placeholder is not a field', () => {
  assert.deepEqual(parseFields(['[ spaced file] [1abc file] [ok-dash file]']), {});
});

test('no file placeholder: the whole row is hidden', () => {
  assert.deepEqual(fileSettings(['[email* email]', '', 'Hello [name]', '<p>[message textarea]</p>']), { row: false, maxFiles: false });
  assert.deepEqual(fileSettings([]), { row: false, maxFiles: false });
});

test('a file placeholder shows the row, without the number of files', () => {
  assert.deepEqual(fileSettings(['', '', '', '<p>[brief* file]</p>']), { row: true, maxFiles: false });
});

test('a files placeholder in any field shows the number of files', () => {
  assert.deepEqual(fileSettings(['', '[attachment files]', '', '']), { row: true, maxFiles: true });
  assert.deepEqual(fileSettings(['[brief file]', '', '', '[attachment* files]']), { row: true, maxFiles: true });
});

test('a type that is not a file type does not count', () => {
  assert.deepEqual(fileSettings(['[file] [files] [brief filez]']), { row: false, maxFiles: false });
});

test('applies the state with the hidden property', () => {
  var row = { hidden: false };
  var max = { hidden: false };

  applyFileSettings(row, max, { row: false, maxFiles: false });
  assert.deepEqual([row.hidden, max.hidden], [true, true]);

  applyFileSettings(row, max, { row: true, maxFiles: false });
  assert.deepEqual([row.hidden, max.hidden], [false, true]);

  applyFileSettings(row, max, { row: true, maxFiles: true });
  assert.deepEqual([row.hidden, max.hidden], [false, false]);

  assert.doesNotThrow(() => applyFileSettings(null, null, { row: true, maxFiles: true }));
});

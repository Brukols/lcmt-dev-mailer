import { test } from 'node:test';
import assert from 'node:assert/strict';
import { errorGroup, clearEvent } from '../../assets/src/lib/field-errors.js';

/**
 * A tiny tree of stand-in elements: enough for closest('label'),
 * closest('fieldset'), the fieldset's own legend, label[for] and the radios
 * of a form. No DOM package needed.
 */
function node(tag, props, children) {
  var el = Object.assign({ tagName: tag.toUpperCase(), parent: null, children: children || [] }, props);
  el.children.forEach(function (c) { c.parent = el; });
  el.closest = function (sel) {
    for (var n = el; n; n = n.parent) if (n.tagName === sel.toUpperCase()) return n;
    return null;
  };
  return el;
}

function all(root) {
  var list = [];
  (function walk(n) { list.push(n); n.children.forEach(walk); })(root);
  return list;
}

function form(children) {
  var f = node('form', {}, children);
  f.querySelector = function (sel) {
    var m = /^label\[for="(.+)"\]$/.exec(sel);
    return all(f).find(function (n) { return m && n.tagName === 'LABEL' && n.htmlFor === m[1]; }) || null;
  };
  f.querySelectorAll = function (sel) {
    assert.equal(sel, 'input[type="radio"]');
    return all(f).filter(function (n) { return n.tagName === 'INPUT' && n.type === 'radio'; });
  };
  return f;
}

/** The fieldset of the theme's quote form: legend, then labels wrapping radios. */
function radioGroup() {
  var r1 = node('input', { type: 'radio', name: 'profil', id: '' });
  var r2 = node('input', { type: 'radio', name: 'profil', id: '' });
  var other = node('input', { type: 'radio', name: 'delai', id: '' });
  var legend = node('legend');
  var l1 = node('label', {}, [r1, node('span')]);
  var l2 = node('label', {}, [r2, node('span')]);
  var fieldset = node('fieldset', {}, [legend, node('div', {}, [l1, l2])]);
  fieldset.querySelector = function (sel) {
    assert.equal(sel, ':scope > legend');
    return fieldset.children.find(function (c) { return c.tagName === 'LEGEND'; }) || null;
  };
  var f = form([fieldset, node('label', {}, [other])]);
  return { f, r1, r2, other, legend, l1, l2 };
}

test('a text field marks itself and its label[for]', () => {
  var input = node('input', { type: 'text', name: 'email', id: 'email' });
  var label = node('label', { htmlFor: 'email' });
  var f = form([label, input]);

  var group = errorGroup(input, f);

  assert.deepEqual(group.controls, [input]);
  assert.deepEqual(group.targets, [input, label]);
});

test('an input wrapped in its label marks that label', () => {
  var input = node('input', { type: 'checkbox', name: 'consent', id: '' });
  var label = node('label', {}, [input]);
  var f = form([label]);

  assert.deepEqual(errorGroup(input, f).targets, [input, label]);
});

test('a radio marks every radio of its group, their labels and the legend', () => {
  var g = radioGroup();
  var group = errorGroup(g.r2, g.f);

  assert.deepEqual(group.controls, [g.r1, g.r2]);
  assert.equal(group.targets.length, 5);
  [g.r1, g.r2, g.l1, g.l2, g.legend].forEach(function (el) { assert.ok(group.targets.includes(el)); });
  assert.ok(!group.targets.includes(g.other), 'another group stays as it is');
});

test('each element is listed once', () => {
  var input = node('input', { type: 'text', name: 'a', id: 'a' });
  var label = node('label', { htmlFor: 'a' }, [input]);
  var f = form([label]);

  assert.deepEqual(errorGroup(input, f).targets, [input, label]);
});

test('works without a form', () => {
  var input = node('input', { type: 'radio', name: 'x', id: 'x' });

  assert.deepEqual(errorGroup(input, null), { controls: [input], targets: [input] });
});

test('choices clear on change, text fields on input', () => {
  assert.equal(clearEvent({ type: 'radio' }), 'change');
  assert.equal(clearEvent({ type: 'checkbox' }), 'change');
  assert.equal(clearEvent({ type: 'file' }), 'change');
  assert.equal(clearEvent({ type: 'select-one' }), 'change');
  assert.equal(clearEvent({ type: 'text' }), 'input');
  assert.equal(clearEvent({ type: 'textarea' }), 'input');
});

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fieldValue } from '../../assets/src/lib/field-value.js';

/** A stand-in for a form control: only what fieldValue() reads. */
function control(props) {
  return Object.assign({ type: 'text', name: 'field', value: '', checked: false }, props);
}

test('a text field gives its trimmed value', () => {
  assert.equal(fieldValue(control({ value: '  Nantes ' }), []), 'Nantes');
});

test('a ticked box gives its value', () => {
  var box = control({ type: 'checkbox', name: 'consent', value: 'Oui', checked: true });

  assert.equal(fieldValue(box, [box]), 'Oui');
});

test('a box left unticked is empty, whatever its value', () => {
  var box = control({ type: 'checkbox', name: 'consent', value: 'Oui' });

  assert.equal(fieldValue(box, [box]), '');
});

test('a radio group gives the value of its checked button', () => {
  var small = control({ type: 'radio', name: 'size', value: 'S' });
  var large = control({ type: 'radio', name: 'size', value: 'L', checked: true });

  assert.equal(fieldValue(small, [small, large]), 'L');
  assert.equal(fieldValue(large, [small, large]), 'L');
});

test('a radio group with no button checked is empty', () => {
  var small = control({ type: 'radio', name: 'size', value: 'S' });
  var large = control({ type: 'radio', name: 'size', value: 'L' });

  assert.equal(fieldValue(small, [small, large]), '');
});

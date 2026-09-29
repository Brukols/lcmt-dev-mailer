import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createDeactivatePrompt } from '../../assets/src/lib/deactivate-dialog.js';

function fakeNode(attrs) {
  var node = {
    attrs: Object.assign({}, attrs),
    listeners: {},
    textContent: '',
    disabled: false,
    setAttribute: function (name, value) {
      this.attrs[name] = String(value);
    },
    getAttribute: function (name) {
      return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
    },
    removeAttribute: function (name) {
      delete this.attrs[name];
    },
    addEventListener: function (type, fn) {
      (this.listeners[type] = this.listeners[type] || []).push(fn);
    },
    fire: function (type, event) {
      var e = Object.assign({ type: type, target: this, defaultPrevented: false, preventDefault: function () { this.defaultPrevented = true; } }, event);
      (this.listeners[type] || []).forEach(function (fn) {
        fn(e);
      });
      return e;
    },
  };
  return node;
}

/**
 * A <dialog> with its checkbox, help line and two buttons.
 */
function fakeDialog(options) {
  var opts = Object.assign({ checked: false }, options);
  var dialog = fakeNode({});
  var checkbox = fakeNode({});
  checkbox.checked = opts.checked;
  checkbox.defaultChecked = opts.checked;
  var help = fakeNode({ 'data-help-off': 'They stay.', 'data-help-on': 'Erased for good.' });
  var cancel = fakeNode({});
  var confirm = fakeNode({ 'data-busy-label': 'Deactivating…' });
  confirm.textContent = 'Deactivate';
  var parts = { '[data-lcmt-delete]': checkbox, '[data-lcmt-help]': help, '[data-lcmt-cancel]': cancel, '[data-lcmt-confirm]': confirm };

  dialog.opened = 0;
  dialog.closed = 0;
  dialog.querySelector = function (selector) {
    return parts[selector] || null;
  };
  dialog.showModal = function () {
    dialog.opened++;
  };
  dialog.close = function () {
    dialog.closed++;
    dialog.fire('close');
  };

  return { dialog: dialog, checkbox: checkbox, help: help, cancel: cancel, confirm: confirm };
}

function handlers() {
  var h = { cancelled: 0, confirmed: [] };
  h.onCancel = function () {
    h.cancelled++;
  };
  h.onConfirm = function (checked) {
    h.confirmed.push(checked);
  };
  return h;
}

test('open shows the modal dialog, unticked and with the keep-them help by default', function () {
  var d = fakeDialog({ checked: false });
  var prompt = createDeactivatePrompt(d.dialog);

  prompt.open(handlers());

  assert.equal(d.dialog.opened, 1);
  assert.equal(d.checkbox.checked, false);
  assert.equal(d.help.textContent, 'They stay.');
});

test('open ticks the box when the stored answer is already yes', function () {
  var d = fakeDialog({ checked: true });
  var prompt = createDeactivatePrompt(d.dialog);

  prompt.open(handlers());

  assert.equal(d.checkbox.checked, true);
  assert.equal(d.help.textContent, 'Erased for good.');
});

test('the help line follows the checkbox', function () {
  var d = fakeDialog({ checked: false });
  createDeactivatePrompt(d.dialog).open(handlers());

  d.checkbox.checked = true;
  d.checkbox.fire('change');
  assert.equal(d.help.textContent, 'Erased for good.');

  d.checkbox.checked = false;
  d.checkbox.fire('change');
  assert.equal(d.help.textContent, 'They stay.');
});

test('Deactivate hands the checkbox state over', function () {
  var d = fakeDialog({ checked: false });
  var h = handlers();
  createDeactivatePrompt(d.dialog).open(h);

  d.confirm.fire('click');
  d.checkbox.checked = true;
  d.confirm.fire('click');

  assert.deepEqual(h.confirmed, [false, true]);
  assert.equal(h.cancelled, 0);
});

test('Cancel closes the dialog and reports it once', function () {
  var d = fakeDialog();
  var h = handlers();
  createDeactivatePrompt(d.dialog).open(h);

  d.cancel.fire('click');

  assert.equal(d.dialog.closed, 1);
  assert.equal(h.cancelled, 1);
  assert.deepEqual(h.confirmed, []);
});

test('a click on the backdrop cancels, a click inside does not', function () {
  var d = fakeDialog();
  var h = handlers();
  createDeactivatePrompt(d.dialog).open(h);

  d.dialog.fire('click', { target: fakeNode({}) });
  assert.equal(d.dialog.closed, 0, 'inside');

  d.dialog.fire('click', { target: d.dialog });
  assert.equal(d.dialog.closed, 1, 'backdrop');
  assert.equal(h.cancelled, 1);
});

test('Escape (the native close) cancels', function () {
  var d = fakeDialog();
  var h = handlers();
  createDeactivatePrompt(d.dialog).open(h);

  d.dialog.fire('close');

  assert.equal(h.cancelled, 1);
});

test('the dialog opens again untouched after a cancel', function () {
  var d = fakeDialog({ checked: false });
  var first = handlers();
  var prompt = createDeactivatePrompt(d.dialog);
  prompt.open(first);
  d.checkbox.checked = true;
  d.checkbox.fire('change');
  d.cancel.fire('click');

  var second = handlers();
  prompt.open(second);

  assert.equal(d.checkbox.checked, false, 'reset to the stored answer');
  assert.equal(d.help.textContent, 'They stay.');
  d.confirm.fire('click');
  assert.deepEqual(second.confirmed, [false]);
  assert.deepEqual(first.confirmed, [], 'old handlers are gone');
});

test('the busy state disables both buttons, says so, and ignores Escape and the backdrop', function () {
  var d = fakeDialog();
  var h = handlers();
  var prompt = createDeactivatePrompt(d.dialog);
  prompt.open(h);

  prompt.setBusy(true);

  assert.equal(d.confirm.disabled, true);
  assert.equal(d.cancel.disabled, true);
  assert.equal(d.confirm.getAttribute('aria-busy'), 'true');
  assert.equal(d.confirm.textContent, 'Deactivating…');

  var escape = d.dialog.fire('cancel');
  assert.equal(escape.defaultPrevented, true, 'Escape is held back');

  d.dialog.fire('click', { target: d.dialog });
  assert.equal(d.dialog.closed, 0, 'the backdrop does nothing');
  assert.equal(h.cancelled, 0);
});

test('close() closes the dialog without reporting a cancel, busy or not', function () {
  var d = fakeDialog();
  var h = handlers();
  var prompt = createDeactivatePrompt(d.dialog);
  prompt.open(h);
  prompt.setBusy(true);

  prompt.close();

  assert.equal(d.dialog.closed, 1);
  assert.equal(h.cancelled, 0);

  var second = handlers();
  var d2 = fakeDialog();
  var p2 = createDeactivatePrompt(d2.dialog);
  p2.open(second);
  p2.close();
  assert.equal(second.cancelled, 0, 'not busy either');
});

test('Escape is left alone when not busy', function () {
  var d = fakeDialog();
  createDeactivatePrompt(d.dialog).open(handlers());

  assert.equal(d.dialog.fire('cancel').defaultPrevented, false);
});

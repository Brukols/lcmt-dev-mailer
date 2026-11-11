/**
 * What turns red when a field is in error: the control (every radio of its
 * group for a radio button), its label[for], the label that wraps it, and
 * the legend of the fieldset it sits in.
 *
 * @param {Element} input
 * @param {?HTMLFormElement} form
 * @returns {{controls: Element[], targets: Element[]}} controls: listened to,
 *   to clear the error; targets: get aria-invalid.
 */
export function errorGroup(input, form) {
  var controls = [input];

  if (input.type === 'radio' && input.name && form) {
    controls = Array.prototype.filter.call(form.querySelectorAll('input[type="radio"]'), function (radio) {
      return radio.name === input.name;
    });

    if (controls.indexOf(input) === -1) controls.push(input);
  }

  var targets = [];

  function add(el) {
    if (el && targets.indexOf(el) === -1) targets.push(el);
  }

  controls.forEach(function (control) { add(control); });

  controls.forEach(function (control) {
    if (control.id && form) add(form.querySelector('label[for="' + control.id + '"]'));

    if (control.closest) {
      add(control.closest('label'));

      var fieldset = control.closest('fieldset');
      if (fieldset) add(fieldset.querySelector(':scope > legend'));
    }
  });

  return { controls: controls, targets: targets };
}

/**
 * The event that means the visitor fixed a field: a choice or a file is
 * changed, text is typed.
 *
 * @param {{type: string}} input
 * @returns {'change'|'input'}
 */
export function clearEvent(input) {
  return /^(radio|checkbox|file|select-one|select-multiple)$/.test(input.type) ? 'change' : 'input';
}

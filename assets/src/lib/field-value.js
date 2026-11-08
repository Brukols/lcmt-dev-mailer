/**
 * The value a form control submits. A checkbox or a radio button only counts
 * when checked: its `value` attribute is there either way, so reading it
 * alone would send an unticked consent box as ticked. A radio group gives the
 * value of its checked button, whichever button of the group is read.
 *
 * @param {{type: string, name: string, value: string, checked: boolean}} input
 * @param {ArrayLike<{type: string, name: string, value: string, checked: boolean}>} inputs every named control of the form
 * @returns {string}
 */
export function fieldValue(input, inputs) {
  if (input.type === 'checkbox') {
    return input.checked ? (input.value || '').trim() : '';
  }

  if (input.type === 'radio') {
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].type === 'radio' && inputs[i].name === input.name && inputs[i].checked) {
        return (inputs[i].value || '').trim();
      }
    }

    return '';
  }

  return (input.value || '').trim();
}

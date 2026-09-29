/**
 * The <dialog> printed by PHP on the Plugins screen: a checkbox, a help line
 * that follows it, Cancel and Deactivate. Expects these hooks inside it:
 * [data-lcmt-delete] (checkbox, its default state is the stored answer),
 * [data-lcmt-help] (with data-help-on and data-help-off),
 * [data-lcmt-cancel] and [data-lcmt-confirm] (with data-busy-label).
 */

/**
 * @param {HTMLDialogElement} dialog
 * @returns {{open: function({onCancel: function, onConfirm: function(boolean)}), setBusy: function(boolean)}}
 */
export function createDeactivatePrompt(dialog) {
  var checkbox = dialog.querySelector('[data-lcmt-delete]');
  var help = dialog.querySelector('[data-lcmt-help]');
  var cancel = dialog.querySelector('[data-lcmt-cancel]');
  var confirm = dialog.querySelector('[data-lcmt-confirm]');
  var idleLabel = confirm.textContent;
  var handlers = null;
  var busy = false;

  function showHelp() {
    help.textContent = help.getAttribute(checkbox.checked ? 'data-help-on' : 'data-help-off');
  }

  checkbox.addEventListener('change', showHelp);

  confirm.addEventListener('click', function () {
    if (busy || !handlers) return;
    handlers.onConfirm(checkbox.checked);
  });

  cancel.addEventListener('click', function () {
    if (!busy) dialog.close();
  });

  // The dialog has no padding of its own: a click that lands on it, not on
  // its content, is a click on the backdrop.
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog && !busy) dialog.close();
  });

  // Escape.
  dialog.addEventListener('cancel', function (event) {
    if (busy) event.preventDefault();
  });

  // Every way of closing it (Cancel, backdrop, Escape) ends here.
  dialog.addEventListener('close', function () {
    var current = handlers;
    handlers = null;
    if (current && !busy) current.onCancel();
  });

  return {
    open: function (next) {
      handlers = next;
      checkbox.checked = checkbox.defaultChecked;
      showHelp();
      dialog.showModal();
    },
    setBusy: function (value) {
      busy = value;
      confirm.disabled = value;
      cancel.disabled = value;

      if (value) {
        confirm.setAttribute('aria-busy', 'true');
        confirm.textContent = confirm.getAttribute('data-busy-label') || idleLabel;
      } else {
        confirm.removeAttribute('aria-busy');
        confirm.textContent = idleLabel;
      }
    },
  };
}

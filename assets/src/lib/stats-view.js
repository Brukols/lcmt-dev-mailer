/**
 * The Chart | Table toggle of the boxes of the Statistics tab. Each box holds
 * two panels ([data-lcmt-view="chart|table"]) and two buttons
 * ([data-lcmt-set-view]). The chosen view is remembered per box in
 * localStorage; the page works the same without it.
 */

var VIEWS = ['chart', 'table'];
var PREFIX = 'lcmt-stats-view:';

export function normalizeView(value) {
  return VIEWS.indexOf(value) !== -1 ? value : 'chart';
}

export function storageKey(id) {
  return PREFIX + id;
}

/**
 * @param {?{getItem: function}} storage null when the browser gives none.
 */
export function readView(storage, id) {
  try {
    return normalizeView(storage ? storage.getItem(storageKey(id)) : null);
  } catch (e) {
    return 'chart';
  }
}

export function writeView(storage, id, view) {
  try {
    if (storage) storage.setItem(storageKey(id), normalizeView(view));
  } catch (e) {
    // Private window or full storage: the choice just is not remembered.
  }
}

function each(list, fn) {
  Array.prototype.forEach.call(list, fn);
}

/**
 * Show one panel of a box and press its button.
 */
export function applyView(box, view) {
  each(box.querySelectorAll('[data-lcmt-view]'), function (panel) {
    if (panel.getAttribute('data-lcmt-view') === view) {
      panel.removeAttribute('hidden');
    } else {
      panel.setAttribute('hidden', '');
    }
  });

  each(box.querySelectorAll('[data-lcmt-set-view]'), function (button) {
    button.setAttribute('aria-pressed', button.getAttribute('data-lcmt-set-view') === view ? 'true' : 'false');
  });
}

/**
 * Restore the remembered view of every box, reveal the toggles (they are
 * hidden without JavaScript, where both panels show) and listen for clicks.
 */
export function initStats(root, storage) {
  root.classList.add('lcmt-stats--js');

  each(root.querySelectorAll('[data-lcmt-stats-box]'), function (box) {
    each(box.querySelectorAll('[data-lcmt-toggle]'), function (toggle) {
      toggle.removeAttribute('hidden');
    });

    applyView(box, readView(storage, box.getAttribute('data-lcmt-stats-box')));
  });

  root.addEventListener('click', function (event) {
    var target = event.target;
    var button = target && target.closest ? target.closest('[data-lcmt-set-view]') : null;
    var box = button ? button.closest('[data-lcmt-stats-box]') : null;

    if (!button || !box) return;

    var view = normalizeView(button.getAttribute('data-lcmt-set-view'));

    applyView(box, view);
    writeView(storage, box.getAttribute('data-lcmt-stats-box'), view);
  });
}

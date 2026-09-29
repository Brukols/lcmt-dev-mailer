import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeView, storageKey, readView, writeView, applyView, initStats } from '../../assets/src/lib/stats-view.js';

function memoryStorage(initial, options) {
  var opts = options || {};
  return {
    data: Object.assign({}, initial),
    getItem: function (key) {
      if (opts.throws) throw new Error('blocked');
      return Object.prototype.hasOwnProperty.call(this.data, key) ? this.data[key] : null;
    },
    setItem: function (key, value) {
      if (opts.throws) throw new Error('full');
      this.data[key] = String(value);
    },
  };
}

/** A tiny element: attributes, children by selector, closest() up the parents. */
function el(attrs, extra) {
  var node = Object.assign(
    {
      attrs: Object.assign({}, attrs),
      parent: null,
      classes: [],
      children: {},
      listeners: {},
      setAttribute: function (name, value) {
        this.attrs[name] = String(value);
      },
      getAttribute: function (name) {
        return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
      },
      removeAttribute: function (name) {
        delete this.attrs[name];
      },
      hasAttribute: function (name) {
        return Object.prototype.hasOwnProperty.call(this.attrs, name);
      },
      querySelectorAll: function (selector) {
        return this.children[selector] || [];
      },
      closest: function (selector) {
        for (var n = this; n; n = n.parent) {
          if (n.selectors && n.selectors.indexOf(selector) !== -1) return n;
        }
        return null;
      },
      classList: {
        add: function (name) {
          node.classes.push(name);
        },
      },
      addEventListener: function (type, fn) {
        (this.listeners[type] = this.listeners[type] || []).push(fn);
      },
    },
    extra
  );
  return node;
}

function buildBox(id) {
  var chart = el({ 'data-lcmt-view': 'chart' });
  var table = el({ 'data-lcmt-view': 'table', hidden: '' });
  var chartBtn = el({ 'data-lcmt-set-view': 'chart', 'aria-pressed': 'true' }, { selectors: ['[data-lcmt-set-view]'] });
  var tableBtn = el({ 'data-lcmt-set-view': 'table', 'aria-pressed': 'false' }, { selectors: ['[data-lcmt-set-view]'] });
  var toggle = el({ 'data-lcmt-toggle': '', hidden: '' });
  var box = el({ 'data-lcmt-stats-box': id }, { selectors: ['[data-lcmt-stats-box]'] });
  box.children['[data-lcmt-view]'] = [chart, table];
  box.children['[data-lcmt-set-view]'] = [chartBtn, tableBtn];
  box.children['[data-lcmt-toggle]'] = [toggle];
  chartBtn.parent = tableBtn.parent = box;
  return { box: box, chart: chart, table: table, chartBtn: chartBtn, tableBtn: tableBtn, toggle: toggle };
}

function buildRoot(ids) {
  var boxes = ids.map(buildBox);
  var root = el({});
  root.children['[data-lcmt-stats-box]'] = boxes.map(function (b) {
    return b.box;
  });
  boxes.forEach(function (b) {
    b.box.parent = root;
  });
  return { root: root, boxes: boxes };
}

test('the chart is the default and anything unknown falls back to it', function () {
  assert.equal(normalizeView('table'), 'table');
  assert.equal(normalizeView('chart'), 'chart');
  assert.equal(normalizeView('pie'), 'chart');
  assert.equal(normalizeView(null), 'chart');
});

test('the choice is remembered per box', function () {
  var storage = memoryStorage({});
  writeView(storage, 'by-month', 'table');

  assert.equal(readView(storage, 'by-month'), 'table');
  assert.equal(readView(storage, 'by-source'), 'chart', 'another box is untouched');
  assert.notEqual(storageKey('by-month'), storageKey('by-source'));
});

test('a stored garbage value reads as the chart', function () {
  assert.equal(readView(memoryStorage({ [storageKey('a')]: 'nope' }), 'a'), 'chart');
});

test('storage that throws never breaks reading or writing', function () {
  var storage = memoryStorage({}, { throws: true });

  assert.equal(readView(storage, 'a'), 'chart');
  assert.doesNotThrow(function () {
    writeView(storage, 'a', 'table');
  });
});

test('no storage at all is fine', function () {
  assert.equal(readView(null, 'a'), 'chart');
  assert.doesNotThrow(function () {
    writeView(null, 'a', 'table');
  });
});

test('applyView shows one panel and presses one button', function () {
  var b = buildBox('a');

  applyView(b.box, 'table');
  assert.equal(b.chart.hasAttribute('hidden'), true);
  assert.equal(b.table.hasAttribute('hidden'), false);
  assert.equal(b.chartBtn.getAttribute('aria-pressed'), 'false');
  assert.equal(b.tableBtn.getAttribute('aria-pressed'), 'true');

  applyView(b.box, 'chart');
  assert.equal(b.chart.hasAttribute('hidden'), false);
  assert.equal(b.table.hasAttribute('hidden'), true);
  assert.equal(b.chartBtn.getAttribute('aria-pressed'), 'true');
  assert.equal(b.tableBtn.getAttribute('aria-pressed'), 'false');
});

test('initStats restores each box, reveals the toggles and marks the root', function () {
  var s = buildRoot(['a', 'b']);
  var storage = memoryStorage({ [storageKey('b')]: 'table' });

  initStats(s.root, storage);

  assert.deepEqual(s.root.classes, ['lcmt-stats--js']);
  assert.equal(s.boxes[0].toggle.hasAttribute('hidden'), false);
  assert.equal(s.boxes[0].table.hasAttribute('hidden'), true, 'a: chart');
  assert.equal(s.boxes[1].chart.hasAttribute('hidden'), true, 'b: table restored');
  assert.equal(s.boxes[1].tableBtn.getAttribute('aria-pressed'), 'true');
});

test('a click on a toggle button switches that box only and stores the choice', function () {
  var s = buildRoot(['a', 'b']);
  var storage = memoryStorage({});
  initStats(s.root, storage);

  s.root.listeners.click[0]({ target: s.boxes[0].tableBtn });

  assert.equal(s.boxes[0].table.hasAttribute('hidden'), false);
  assert.equal(s.boxes[1].table.hasAttribute('hidden'), true);
  assert.equal(storage.data[storageKey('a')], 'table');
  assert.equal(storageKey('b') in storage.data, false);

  s.root.listeners.click[0]({ target: s.boxes[0].chartBtn });
  assert.equal(s.boxes[0].chart.hasAttribute('hidden'), false);
  assert.equal(storage.data[storageKey('a')], 'chart');
});

test('a click elsewhere in the page is ignored', function () {
  var s = buildRoot(['a']);
  initStats(s.root, memoryStorage({}));

  assert.doesNotThrow(function () {
    s.root.listeners.click[0]({ target: el({}) });
  });
  assert.equal(s.boxes[0].table.hasAttribute('hidden'), true);
});

test('the toggle works when storage is unavailable', function () {
  var s = buildRoot(['a']);
  initStats(s.root, null);

  s.root.listeners.click[0]({ target: s.boxes[0].tableBtn });

  assert.equal(s.boxes[0].table.hasAttribute('hidden'), false);
});

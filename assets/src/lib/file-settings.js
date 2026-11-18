/**
 * The "File uploads" row of the mail template screen follows the template:
 * shown only when To, Reply-To, Subject or Content holds a [name file] or
 * [name files] placeholder, its "Maximum number of files" only with a
 * [name files] one. Placeholders are read with the rules of
 * FieldParser::parse() (PHP), which renders the same state first.
 */

// FieldParser::PATTERN.
var PATTERN = /\[([a-zA-Z_][a-zA-Z0-9_]*)(\*)?(?:\s+([a-zA-Z]+))?\]/g;

// FieldParser::VALID_TYPES.
var VALID_TYPES = ['text', 'email', 'tel', 'phone', 'url', 'number', 'password', 'date', 'textarea', 'hidden', 'file', 'files'];

function normalizeType(type) {
  if (type === 'phone') return 'tel';

  return VALID_TYPES.indexOf(type) === -1 ? 'text' : type;
}

/**
 * The fields of the placeholders in these texts, as FieldParser::parse():
 * a repeated field is required if any occurrence is, and takes the first
 * explicit type over the default text.
 *
 * @param {string[]} sources
 * @returns {Object<string, {name: string, required: boolean, type: string}>}
 */
export function parseFields(sources) {
  var fields = {};
  var combined = (sources || []).join(' ');
  var match;

  PATTERN.lastIndex = 0;

  while ((match = PATTERN.exec(combined)) !== null) {
    var name = match[1];
    var required = match[2] === '*';
    var type = normalizeType(match[3] ? match[3].toLowerCase() : 'text');

    if (Object.prototype.hasOwnProperty.call(fields, name)) {
      if (required) fields[name].required = true;
      if (type !== 'text' && fields[name].type === 'text') fields[name].type = type;
      continue;
    }

    fields[name] = { name: name, required: required, type: type };
  }

  return fields;
}

/**
 * @param {string[]} sources To, Reply-To, Subject and Content.
 * @returns {{row: boolean, maxFiles: boolean}} row: a file or files field; maxFiles: a files field.
 */
export function fileSettings(sources) {
  var fields = parseFields(sources);
  var state = { row: false, maxFiles: false };

  Object.keys(fields).forEach(function (name) {
    if (fields[name].type === 'file' || fields[name].type === 'files') state.row = true;
    if (fields[name].type === 'files') state.maxFiles = true;
  });

  return state;
}

/**
 * Show or hide the row and the "Maximum number of files" setting with the
 * hidden property (hidden settings are still posted, so they keep their
 * values).
 *
 * @param {?{hidden: boolean}} row
 * @param {?{hidden: boolean}} maxFiles
 * @param {{row: boolean, maxFiles: boolean}} state
 */
export function applyFileSettings(row, maxFiles, state) {
  if (row) row.hidden = !state.row;
  if (maxFiles) maxFiles.hidden = !state.maxFiles;
}

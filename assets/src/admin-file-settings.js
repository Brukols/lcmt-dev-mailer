/**
 * LCMT Mailer — mail template screen: shows the "File uploads" row only
 * while To, Reply-To, Subject or Content holds a [name file] / [name files]
 * placeholder, and its "Maximum number of files" only with [name files]
 * (see lib/file-settings.js). The server renders the same state first.
 */
import { fileSettings, applyFileSettings } from './lib/file-settings';

(function () {
  'use strict';

  var row = document.getElementById('lcmt-file-uploads');
  if (!row) return;

  var maxFiles = document.getElementById('lcmt-file-max-files');
  var ids = ['lcmt_mail_to', 'lcmt_mail_reply_to', 'lcmt_mail_subject', 'lcmt_mail_content'];

  // The visual editor keeps the content in TinyMCE until it saves it back
  // to the textarea: read it from there while it is shown.
  function contentEditor() {
    var mce = window.tinymce;
    var editor = mce && mce.get ? mce.get('lcmt_mail_content') : null;

    return editor && !editor.isHidden() ? editor : null;
  }

  function sources() {
    return ids.map(function (id) {
      var editor = id === 'lcmt_mail_content' ? contentEditor() : null;
      if (editor) return editor.getContent();

      var el = document.getElementById(id);
      return el ? el.value : '';
    });
  }

  function refresh() {
    applyFileSettings(row, maxFiles, fileSettings(sources()));
  }

  ids.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', refresh);
  });

  // TinyMCE loads after this script and may create the editor later (when
  // the Visual tab is first opened): hook it once it is there.
  function watchEditor(editor) {
    if (!editor || editor.id !== 'lcmt_mail_content' || editor.lcmtFileSettings) return;

    editor.lcmtFileSettings = true;
    editor.on('input keyup change NodeChange SetContent Undo Redo', refresh);
  }

  var hooked = false;

  function hookTinymce() {
    var mce = window.tinymce;
    if (!mce) return;

    if (mce.get) watchEditor(mce.get('lcmt_mail_content'));

    if (!hooked && mce.on) {
      hooked = true;
      mce.on('AddEditor', function (e) { watchEditor(e.editor); });
    }
  }

  hookTinymce();
  window.addEventListener('load', function () {
    hookTinymce();
    refresh();
  });

  // Switching between the Visual and Text tabs.
  document.addEventListener('click', function (e) {
    if (e.target && e.target.id && /^lcmt_mail_content-(tmce|html)$/.test(e.target.id)) setTimeout(refresh, 0);
  });
})();

/* global drupalSettings jQuery Drupal once */

(function ($, Drupal, H5P, H5PEditor) {
  var initialized;
  var formStates = new WeakMap();
  var frameRecords = new WeakMap();
  var rootRecords = new WeakMap();

  /**
   * Register an editor with one coordinated submit handler per Drupal form.
   *
   * @param {HTMLFormElement} form
   *   The Drupal form containing this editor.
   * @param {Object} record
   *   Editor instance and its hidden fields.
   */
  function registerEditor(form, record) {
    var state = formStates.get(form);
    if (!state) {
      state = {records: new Set(), handler: null};
      state.handler = function (event) {
        var invalid = false;
        state.records.forEach(function (editorRecord) {
          if (editorRecord.frame && !editorRecord.frame.isConnected) {
            // AJAX replacement can detach a H5P iframe without unloading the
            // containing Drupal form. Discard that stale editor instance.
            unregisterEditor(editorRecord);
            return;
          }
          var library = editorRecord.editor.getLibrary();
          if (library) {
            var frameDocument = editorRecord.editor.iframeWindow.document;
            if (frameDocument.activeElement) {
              frameDocument.activeElement.blur();
            }
            var params = editorRecord.editor.getParams();
            editorRecord.$library.val(library);
            editorRecord.$params.val(JSON.stringify(params));
            if (params && params.params !== undefined && !editorRecord.editor.isMainTitleSet()) {
              event.preventDefault();
              showEditorError(editorRecord, 'missing-title');
              invalid = true;
            }
          }
          else {
            // Clearing an existing activity is a valid edit. Keep the hidden
            // Form API values in sync and do not ask the empty editor to save.
            editorRecord.$library.val('');
            editorRecord.$params.val('');
          }
        });
        if (!state.records.size) {
          $(form).off('submit.acultaH5PEditor', state.handler);
          formStates.delete(form);
          return;
        }
        if (invalid) {
          return;
        }
      };
      formStates.set(form, state);
      $(form).on('submit.acultaH5PEditor', state.handler);
    }
    state.records.add(record);
    rootRecords.set(record.root, record);
  }

  /** Shows an accessible validation error next to the affected editor. */
  function showEditorError(record, error) {
    var messages = {
      'missing-title': Drupal.t('Preencha o título principal da atividade.')
    };
    var message = messages[error] || Drupal.t('Não foi possível validar a atividade H5P. Revise os campos e tente novamente.');
    var $target = record.frame ? $(record.frame) : record.$root;
    var $alert = $target.next('[data-h5p-editor-error]');
    if (!$alert.length) {
      $alert = $('<div>', {
        'data-h5p-editor-error': '',
        'class': 'messages messages--error',
        'role': 'alert'
      }).insertAfter($target);
    }
    $alert.text(message);
  }

  /** Detaches an editor record from its containing Drupal form. */
  function unregisterEditor(record) {
    var form = record.$form[0];
    var state = formStates.get(form);
    if (state) {
      state.records.delete(record);
      if (!state.records.size) {
        record.$form.off('submit.acultaH5PEditor', state.handler);
        formStates.delete(form);
      }
    }
    if (record.frame) {
      frameRecords.delete(record.frame);
    }
    rootRecords.delete(record.root);
  }

  /**
   * One time setup of the H5PEditor
   *
   * @param Object settings from drupal
   */
  H5PEditor.init = function () {
    if (initialized) {
      return; // Prevent multi init
    }
    initialized = true;

    // Set up editor settings
    H5PEditor.$ = H5P.jQuery;
    H5PEditor.baseUrl = drupalSettings.path.baseUrl;
    H5PEditor.basePath = drupalSettings.h5peditor.libraryPath;
    H5PEditor.contentLanguage = drupalSettings.h5peditor.language;
    mapProperties(H5PEditor, drupalSettings.h5peditor,
      ['contentId', 'fileIcon', 'relativeUrl', 'contentRelUrl', 'editorRelUrl', 'apiVersion', 'copyrightSemantics', 'metadataSemantics', 'assets']);
  };

  // Init editors
  Drupal.behaviors.H5PEditor = {
    attach: function (context, settings) {
      $(once('H5PEditor','.h5p-editor', context)).each(function () {
        H5PEditor.init();

        // Grab data values specific for editor instance.
        var $this = $(this);
        var parametersFieldId = $this.data('parametersid');
        var libraryFieldId = $this.data('libraryid');
        var contentId = $this.data('contentId');
        var $form = $this.parents('form');

        // Locate parameters field
        var $params = $('#' + parametersFieldId, context);

        // Locate library field
        var $library = $('#' + libraryFieldId, context);

        var record = {
          root: this,
          $root: $this,
          $form: $form,
          $library: $library,
          $params: $params,
          editor: null,
          frame: null
        };

        // Create new editor
        record.editor = new H5PEditor.Editor($library.val(), $params.val(), this, function () {
          record.frame = this.frameElement;
          frameRecords.set(record.frame, record);
          var iframeH5PEditor = this.H5PEditor;
          iframeH5PEditor.contentId = (contentId ? contentId : undefined);
          iframeH5PEditor.ajaxPath = settings.h5peditor.ajaxPath.replace(':contentId', (contentId ? contentId : 0));
          iframeH5PEditor.filesPath = settings.h5peditor.filesPath + '/editor';

          /**
           * Help build URLs for AJAX requests
           */
          iframeH5PEditor.getAjaxUrl = function (action, parameters) {
            var url = iframeH5PEditor.ajaxPath + action;

            if (parameters !== undefined) {
              for (var key in parameters) {
                url += '/' + parameters[key];
              }
            }

            return url;
          };
        });
        registerEditor($form[0], record);
      });
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'serialize') {
        var $iframes = $(context).find('.h5p-editor-iframe');
        if ($(context).is('.h5p-editor-iframe')) {
          $iframes = $iframes.add(context);
        }
        $iframes.each(function () {
          var record = frameRecords.get(this);
          if (record && record.editor) {
            var frameDocument = record.editor.iframeWindow.document;
            if (frameDocument.activeElement) {
              frameDocument.activeElement.blur();
            }
            var library = record.editor.getLibrary();
            var params = record.editor.getParams(true);
            if (library && params && params.params && params.metadata) {
              record.$library.val(library);
              record.$params.val(JSON.stringify(params));
            }
          }
        });
      }
      else if (trigger === 'unload') {
        var $iframes = $(context).find('.h5p-editor-iframe');
        if ($(context).is('.h5p-editor-iframe')) {
          $iframes = $iframes.add(context);
        }
        $iframes.each(function () {
          var frame = this;
          var record = frameRecords.get(frame);
          if (record) {
            // Drupal calls detach before the AJAX command removes its old
            // subtree. Defer the connectivity check so surviving editors in
            // the same form keep their registration.
            window.setTimeout(function () {
              if (!frame.isConnected) {
                unregisterEditor(record);
              }
            }, 0);
          }
        });
        var $editors = $(context).find('.h5p-editor');
        if ($(context).is('.h5p-editor')) {
          $editors = $editors.add(context);
        }
        $editors.each(function () {
          var record = rootRecords.get(this);
          if (record) {
            var root = this;
            // H5P replaces its placeholder root with the iframe during
            // initialization. Drupal may report the placeholder as unloaded
            // while that iframe is still active, so only discard the record
            // after confirming that neither node remains in the document.
            window.setTimeout(function () {
              if (!root.isConnected && (!record.frame || !record.frame.isConnected)) {
                unregisterEditor(record);
              }
            }, 0);
          }
        });
        once.remove('H5PEditor', $editors);
      }
    }
  };

  /**
   * Map properties from one object to the other
   * @private
   * @param {Object} to
   * @param {Object} from
   * @param {Array} props
   */
  var mapProperties = function (to, from, props) {
    for (var i = 0; i < props.length; i++) {
      var prop = props[i];
      to[prop] = from[prop];
    }
  };

})(jQuery, Drupal, H5P, H5PEditor);

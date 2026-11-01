/**
 * @file
 * CKEditor 5 button for the Forum's contextual private attachment upload.
 */
(function (dll) {
  const { Plugin } = dll('./src/core.js');
  const { ButtonView } = dll('./src/ui.js');

  class AcultaForumAttachmentUI extends Plugin {

    static get pluginName() {
      return 'AcultaForumAttachmentUI';
    }

    init() {
      const editor = this.editor;
      const options = editor.config.get('acultaForumAttachment');
      if (!options) {
        return;
      }
      editor.ui.componentFactory.add('acultaForumAttachment', (locale) => {
        const view = new ButtonView(locale);
        view.set({
          label: options.label,
          withText: true,
          tooltip: true,
        });
        view.on('execute', () => {
          Drupal.ckeditor5.openDialog(options.dialogUrl, ({ attributes }) => {
            editor.execute('insertEntityEmbed', attributes);
          });
        });
        return view;
      });
      editor.ui.componentFactory.add('acultaForumGallery', (locale) => {
        const view = new ButtonView(locale);
        view.set({
          label: options.galleryLabel,
          withText: true,
          tooltip: true,
        });
        view.on('execute', () => {
          Drupal.ckeditor5.openDialog(options.galleryDialogUrl, ({ attributes }) => {
            editor.execute('insertEntityEmbed', attributes);
          });
        });
        return view;
      });
    }

  }

  window.CKEditor5 = window.CKEditor5 || {};
  window.CKEditor5.acultaForumAttachment = { AcultaForumAttachmentUI };
})(window.CKEditor5.dll);

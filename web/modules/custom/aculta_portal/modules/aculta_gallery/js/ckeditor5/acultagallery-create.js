/**
 * @file
 * Botão "Criar galeria" do CKEditor 5 (ACULTA Gallery).
 *
 * Plugin sem etapa de compilação: usa o bundle do Core (CKEditor5.dll), como os plugins do Core. Abre o
 * diálogo do Core com o formulário de galeria; ao salvar, delega a inserção ao comando insertEntityEmbed
 * do Entity Embed. Não guarda lista de imagens no texto.
 */
(function (dll) {
  const { Plugin } = dll('./src/core.js');
  const { ButtonView } = dll('./src/ui.js');

  class AcultaGalleryCreateUI extends Plugin {

    static get pluginName() {
      return 'AcultaGalleryCreateUI';
    }

    init() {
      const editor = this.editor;
      const options = editor.config.get('acultaGalleryCreate');
      if (!options) {
        return;
      }
      editor.ui.componentFactory.add('acultaGalleryCreate', (locale) => {
        const view = new ButtonView(locale);
        view.set({
          label: options.label,
          withText: true,
          tooltip: true,
        });
        view.on('execute', () => {
          Drupal.ckeditor5.openDialog(
            options.dialogUrl,
            ({ attributes }) => {
              editor.execute('insertEntityEmbed', attributes);
            },
            { ...options.dialogSettings },
          );
        });
        return view;
      });
    }

  }

  window.CKEditor5 = window.CKEditor5 || {};
  window.CKEditor5.acultaGallery = { AcultaGalleryCreateUI };
})(window.CKEditor5.dll);

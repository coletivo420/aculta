/**
 * @file
 * Modelo de imagem no CKEditor 5 (ACULTA Image, plugin acultaImage.ImageModel).
 *
 * JavaScript simples, sem build: usa os globais que o core do Drupal já expõe (CKEditor5.dll e CKEditor5.image).
 * Guarda o modelo escolhido num atributo do elemento de imagem (acultaModelo), que é exportado como data-aculta-modelo
 * no <img>. O filtro aculta_imagem_modelo lê esse atributo e monta o <figure> com os estilos do modelo.
 *
 * Ainda não faz parte desta entrega: legenda e crédito na UI do editor (ver docs/MODELOS-DE-IMAGEM.md).
 */
(function (CKEditor5) {
  'use strict';

  const { Plugin } = CKEditor5.dll('./src/core.js');
  const { createDropdown, addListToDropdown, Model } = CKEditor5.dll('./src/ui.js');

  /** Modelos aceitos (mesmos nomes de ImageCatalog::MODELOS). */
  const MODELOS = [
    { value: 'pequena', label: 'Pequena' },
    { value: 'padrao', label: 'Padrão' },
    { value: 'larga', label: 'Larga' },
    { value: 'destaque', label: 'Destaque' },
  ];

  /** Elementos de imagem do modelo de dados do core. */
  const ELEMENTOS_IMAGEM = ['imageBlock', 'imageInline'];

  /**
   * Plugin do atributo acultaModelo e do seletor na barra de ferramentas.
   */
  class ImageModel extends Plugin {

    static get pluginName() {
      return 'ImageModel';
    }

    init() {
      const editor = this.editor;

      ELEMENTOS_IMAGEM.forEach((nome) => {
        editor.model.schema.extend(nome, { allowAttributes: ['acultaModelo'] });
      });

      // Leitura: <img data-aculta-modelo="..."> vira o atributo do modelo de dados.
      editor.conversion.for('upcast').attributeToAttribute({
        view: { name: 'img', key: 'data-aculta-modelo' },
        model: 'acultaModelo',
      });

      // Gravação: o atributo vai para o <img>, que é o elemento que o filtro do servidor lê.
      editor.conversion.for('downcast').add((dispatcher) => {
        dispatcher.on('attribute:acultaModelo', (evt, data, conversionApi) => {
          const { writer, mapper } = conversionApi;
          if (!ELEMENTOS_IMAGEM.includes(data.item.name)) {
            return;
          }
          const figura = mapper.toViewElement(data.item);
          const img = figura && (figura.name === 'img' ? figura : [...figura.getChildren()].find((filho) => filho.name === 'img'));
          if (!img) {
            return;
          }
          if (data.attributeNewValue === null) {
            writer.removeAttribute('data-aculta-modelo', img);
          }
          else {
            writer.setAttribute('data-aculta-modelo', data.attributeNewValue, img);
          }
        });
      });

      editor.ui.componentFactory.add('acultaImageModel', (locale) => {
        const dropdown = createDropdown(locale);
        dropdown.buttonView.set({ label: 'Modelo de imagem', tooltip: true, withText: true });

        // Modelos oferecidos por este editor (configuração do plugin); sem configuração, todos.
        const permitidos = editor.config.get('acultaImageModel.models') || MODELOS.map((modelo) => modelo.value);
        const items = MODELOS.filter((modelo) => permitidos.includes(modelo.value)).map((modelo) => ({
          type: 'button',
          model: new Model({ commandParam: modelo.value, label: modelo.label, withText: true }),
        }));
        addListToDropdown(dropdown, items);

        dropdown.on('execute', (evt) => {
          const selecionado = this._imagemSelecionada();
          if (!selecionado) {
            return;
          }
          editor.model.change((writer) => {
            writer.setAttribute('acultaModelo', evt.source.commandParam, selecionado);
          });
          editor.editing.view.focus();
        });

        return dropdown;
      });
    }

    /**
     * Imagem selecionada no modelo de dados, ou null quando a seleção não é uma imagem.
     */
    _imagemSelecionada() {
      const elemento = this.editor.model.document.selection.getSelectedElement();
      return elemento && ELEMENTOS_IMAGEM.includes(elemento.name) ? elemento : null;
    }

  }

  (window.CKEditor5 = window.CKEditor5 || {}).acultaImage = { ImageModel };

})(window.CKEditor5);

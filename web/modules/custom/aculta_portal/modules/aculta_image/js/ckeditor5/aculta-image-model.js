/**
 * @file
 * Modelo de imagem no CKEditor 5 (ACULTA Image, plugin acultaImage.ImageModel).
 *
 * JavaScript simples, sem build: usa os globais que o core do Drupal já expõe (CKEditor5.dll e CKEditor5.image).
 * Guarda o modelo escolhido num atributo do elemento de imagem (acultaModelo), que é exportado como data-aculta-modelo
 * no <img>. O filtro aculta_imagem_modelo lê esse atributo e monta o <figure> com os estilos do modelo.
 *
 * A legenda é editada num formulário contextual e gravada como data-aculta-legenda no <img>.
 */
(function (CKEditor5) {
  'use strict';

  const { Plugin } = CKEditor5.dll('./src/core.js');
  const {
    ButtonView,
    ContextualBalloon,
    DropdownButtonView,
    LabeledFieldView,
    View,
    ViewModel,
    addListToDropdown,
    clickOutsideHandler,
    createDropdown,
    createLabeledInputText,
    submitHandler,
  } = CKEditor5.dll('./src/ui.js');
  const { Collection, KeystrokeHandler } = CKEditor5.dll('./src/utils.js');

  /** Modelos aceitos (mesmos nomes de ImageCatalog::MODELOS). */
  const MODELOS = [
    { value: 'pequena', label: 'Pequena' },
    { value: 'padrao', label: 'Padrão' },
    { value: 'larga', label: 'Larga' },
    { value: 'destaque', label: 'Destaque' },
  ];

  /** Elementos de imagem do modelo de dados do core. */
  const ELEMENTOS_IMAGEM = ['imageBlock', 'imageInline'];

  /** Formulário simples para editar a legenda da imagem selecionada. */
  class LegendaFormView extends View {

    constructor(locale) {
      super(locale);
      this.input = new LabeledFieldView(locale, createLabeledInputText);
      this.input.label = 'Legenda da imagem';
      this.input.fieldView.placeholder = 'Digite a legenda';
      this.keystrokes = new KeystrokeHandler();
      this.saveButton = new ButtonView(locale);
      this.saveButton.set({ label: 'Salvar legenda', withText: true, type: 'submit' });
      this.cancelButton = new ButtonView(locale);
      this.cancelButton.set({ label: 'Cancelar', withText: true });
      this.cancelButton.delegate('execute').to(this, 'cancel');

      this.setTemplate({
        tag: 'form',
        attributes: {
          class: ['ck', 'ck-aculta-image-caption-form', 'ck-responsive-form'],
          tabindex: '-1',
        },
        children: [this.input, this.saveButton, this.cancelButton],
      });
    }

    render() {
      super.render();
      this.keystrokes.listenTo(this.element);
      submitHandler({ view: this });
      this.keystrokes.set('Esc', (data, cancel) => {
        this.fire('cancel');
        cancel();
      });
    }

    destroy() {
      super.destroy();
      this.keystrokes.destroy();
    }

  }

  /**
   * Plugin do atributo acultaModelo e do seletor na barra de ferramentas.
   */
  class ImageModel extends Plugin {

    static get requires() {
      // Register both image model elements before extending their schema.
      return [
        CKEditor5.image.ImageBlockEditing,
        CKEditor5.image.ImageInlineEditing,
        ContextualBalloon,
      ];
    }

    static get pluginName() {
      return 'ImageModel';
    }

    init() {
      const editor = this.editor;

      ELEMENTOS_IMAGEM.forEach((nome) => {
        editor.model.schema.extend(nome, { allowAttributes: ['acultaModelo', 'acultaLegenda'] });
      });

      // Leitura: <img data-aculta-modelo="..."> vira o atributo do modelo de dados.
      editor.conversion.for('upcast').attributeToAttribute({
        view: { name: 'img', key: 'data-aculta-modelo' },
        model: 'acultaModelo',
      });
      editor.conversion.for('upcast').attributeToAttribute({
        view: { name: 'img', key: 'data-aculta-legenda' },
        model: 'acultaLegenda',
      });

      // Gravação: o atributo vai para o <img>, que é o elemento que o filtro do servidor lê.
      editor.conversion.for('downcast').add((dispatcher) => {
        ['acultaModelo', 'acultaLegenda'].forEach((nomeAtributo) => dispatcher.on(`attribute:${nomeAtributo}`, (evt, data, conversionApi) => {
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
            writer.removeAttribute(nomeAtributo === 'acultaModelo' ? 'data-aculta-modelo' : 'data-aculta-legenda', img);
          }
          else {
            writer.setAttribute(nomeAtributo === 'acultaModelo' ? 'data-aculta-modelo' : 'data-aculta-legenda', data.attributeNewValue, img);
          }
        }));
      });

      const permitidos = editor.config.get('acultaImageModel.models') || MODELOS.map((modelo) => modelo.value);
      const modelosDisponiveis = MODELOS.filter((modelo) => permitidos.includes(modelo.value));
      editor.ui.componentFactory.add('acultaImageModel', (locale) => {
        const dropdown = createDropdown(locale, DropdownButtonView);
        const opcoes = new Collection();
        const botao = dropdown.buttonView;
        botao.set({ label: 'Modelo da imagem', tooltip: true, withText: true });

        modelosDisponiveis.forEach((modelo) => {
          opcoes.add({
            type: 'button',
            model: new ViewModel({
              commandValue: modelo.value,
              label: `Imagem: ${modelo.label}`,
              withText: true,
            }),
          });
        });
        addListToDropdown(dropdown, opcoes);
        this.listenTo(dropdown, 'execute', (evt) => {
          const selecionado = this._imagemSelecionada();
          if (!selecionado) {
            return;
          }
          editor.model.change((writer) => writer.setAttribute('acultaModelo', evt.source.commandValue, selecionado));
          editor.editing.view.focus();
        });
        this.listenTo(editor.ui, 'update', () => {
          const selecionado = this._imagemSelecionada();
          const modeloAtual = selecionado && modelosDisponiveis.find((modelo) => modelo.value === selecionado.getAttribute('acultaModelo'));
          botao.label = modeloAtual ? `Imagem: ${modeloAtual.label}` : 'Modelo da imagem';
        });
        return dropdown;
      });

      this._balloon = editor.plugins.get(ContextualBalloon);
      this._captionForm = new LegendaFormView(editor.locale);
      this._captionForm.render();
      clickOutsideHandler({
        emitter: this._captionForm,
        activator: () => this._isCaptionFormVisible,
        contextElements: [this._balloon.view.element],
        callback: () => this._ocultarLegenda(),
      });
      this._captionForm.on('submit', () => {
        const selecionado = this._imagemSelecionada();
        if (selecionado) {
          const legenda = this._captionForm.input.fieldView.element.value.trim();
          editor.model.change((writer) => {
            if (legenda === '') {
              writer.removeAttribute('acultaLegenda', selecionado);
            }
            else {
              writer.setAttribute('acultaLegenda', legenda, selecionado);
            }
          });
        }
        this._ocultarLegenda();
      });
      this._captionForm.on('cancel', () => this._ocultarLegenda());

      editor.ui.componentFactory.add('acultaImageLegenda', (locale) => {
        const botao = new ButtonView(locale);
        botao.set({ label: 'Editar legenda', tooltip: true, withText: true });
        botao.on('execute', () => this._mostrarLegenda());
        return botao;
      });

      this.listenTo(editor.ui, 'update', () => {
        if (this._isCaptionFormVisible && !this._imagemSelecionada()) {
          this._ocultarLegenda();
        }
      });
    }

    destroy() {
      super.destroy();
      this._captionForm.destroy();
    }

    _mostrarLegenda() {
      const selecionado = this._imagemSelecionada();
      if (!selecionado) {
        return;
      }
      const input = this._captionForm.input.fieldView;
      input.element.value = selecionado.getAttribute('acultaLegenda') || '';
      input.value = input.element.value;
      if (!this._isCaptionFormVisible) {
        this._balloon.add({
          view: this._captionForm,
          position: { target: this.editor.ui.getEditableElement() },
        });
      }
      this._isCaptionFormVisible = true;
      input.focus();
    }

    _ocultarLegenda() {
      if (this._isCaptionFormVisible) {
        this._balloon.remove(this._captionForm);
        this._isCaptionFormVisible = false;
      }
      this.editor.editing.view.focus();
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

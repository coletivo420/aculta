/**
 * @file
 * Seletor de emojis Fluent no CKEditor 5 (ACULTA Editor, plugin FluentEmoji).
 *
 * Usa os globais que o core do Drupal já expõe (CKEditor5.dll), sem build.
 * Insere no modelo de documento apenas o caractere Unicode nativo. O filtro
 * Drupal fluent_emoji é quem troca o glifo pelo SVG colorido na saída.
 *
 * O catálogo, a URL base dos SVGs e os rótulos chegam por configuração dinâmica
 * em editor.config.acultaFluentEmoji, montada por
 * \Drupal\aculta_editor\Plugin\CKEditor5Plugin\FluentEmoji.
 */
(function (CKEditor5) {
  'use strict';

  const { Plugin } = CKEditor5.dll('./src/core.js');
  const { View, createDropdown } = CKEditor5.dll('./src/ui.js');

  /**
   * Ícone do botão na toolbar: rosto sorridente em currentColor.
   */
  const EMOJI_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
    + '<circle cx="10" cy="10" r="7.25" stroke="currentColor" stroke-width="1.5"/>'
    + '<circle cx="7.25" cy="8.25" r="1.1" fill="currentColor"/>'
    + '<circle cx="12.75" cy="8.25" r="1.1" fill="currentColor"/>'
    + '<path d="M6.6 11.4c.9 1.4 2 2.1 3.4 2.1s2.5-.7 3.4-2.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
    + '</svg>';

  /**
   * Painel com busca instantânea e grade de emojis agrupada por categoria.
   */
  class EmojiPanelView extends View {

    constructor(locale, config, callbacks) {
      super(locale);
      this._config = config;
      this._callbacks = callbacks;
      this._items = [];
      this._groups = [];
      this.setTemplate({
        tag: 'div',
        attributes: {
          class: ['ck', 'ck-aculta-fluent-emoji-panel'],
          tabindex: '-1',
        },
      });
    }

    render() {
      super.render();

      const doc = this.element.ownerDocument;
      const labels = this._config.labels || {};

      const search = doc.createElement('input');
      search.type = 'search';
      search.className = 'ck ck-input ck-aculta-fluent-emoji-search';
      search.placeholder = labels.search || 'Buscar emoji';
      search.setAttribute('aria-label', labels.search || 'Buscar emoji');
      search.setAttribute('autocomplete', 'off');
      this.element.appendChild(search);

      const grid = doc.createElement('div');
      grid.className = 'ck-aculta-fluent-emoji-grid';
      grid.setAttribute('role', 'listbox');
      grid.setAttribute('aria-label', labels.list || 'Emojis disponíveis');
      this.element.appendChild(grid);

      const empty = doc.createElement('p');
      empty.className = 'ck-aculta-fluent-emoji-empty';
      empty.textContent = labels.empty || 'Nenhum emoji encontrado.';
      empty.hidden = true;
      this.element.appendChild(empty);

      this._buildGrid(doc, grid);

      search.addEventListener('input', () => this._filter(search.value));
      search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          const firstVisible = this._items.find((item) => !item.button.hidden);
          if (firstVisible) {
            firstVisible.button.click();
          }
        }
        else if (event.key === 'Escape') {
          event.preventDefault();
          this._callbacks.close();
        }
      });

      this._search = search;
      this._grid = grid;
      this._empty = empty;
    }

    /**
     * Monta os botões agrupados pela ordem de categorias do catálogo.
     */
    _buildGrid(doc, grid) {
      const categories = (this._config.labels && this._config.labels.categories) || {};
      const baseUrl = (this._config.svgBaseUrl || '').replace(/\/+$/, '');
      const byCategory = new Map();

      this._config.catalog.forEach((entry) => {
        if (!byCategory.has(entry.category)) {
          byCategory.set(entry.category, []);
        }
        byCategory.get(entry.category).push(entry);
      });

      byCategory.forEach((entries, category) => {
        const heading = doc.createElement('div');
        heading.className = 'ck-aculta-fluent-emoji-category';
        heading.textContent = categories[category] || category;
        heading.setAttribute('role', 'presentation');
        grid.appendChild(heading);

        const group = { heading, items: [] };
        entries.forEach((entry) => {
          const button = doc.createElement('button');
          button.type = 'button';
          button.className = 'ck ck-button ck-aculta-fluent-emoji-item';
          button.setAttribute('role', 'option');
          button.setAttribute('aria-label', entry.name);
          button.title = entry.name;

          const image = doc.createElement('img');
          image.src = baseUrl + '/' + entry.svg;
          image.alt = '';
          image.width = 24;
          image.height = 24;
          image.loading = 'lazy';
          button.appendChild(image);

          button.addEventListener('click', () => this._callbacks.insert(entry.glyph));

          grid.appendChild(button);
          const searchable = [entry.name].concat(entry.keywords || []).join(' ').toLocaleLowerCase('pt-BR');
          const item = { button, searchable };
          group.items.push(item);
          this._items.push(item);
        });
        this._groups.push(group);
      });
    }

    /**
     * Oculta os botões que não correspondem ao termo digitado.
     */
    _filter(term) {
      const normalized = term.toLocaleLowerCase('pt-BR').trim();
      let visibleTotal = 0;
      this._groups.forEach((group) => {
        let visible = 0;
        group.items.forEach((item) => {
          const match = normalized === '' || item.searchable.includes(normalized);
          item.button.hidden = !match;
          if (match) {
            visible++;
          }
        });
        group.heading.hidden = visible === 0;
        visibleTotal += visible;
      });
      this._empty.hidden = visibleTotal > 0;
    }

    /**
     * Coloca o foco na busca ao abrir o painel.
     */
    focus() {
      if (this._search) {
        this._search.focus();
        this._search.select();
      }
    }

  }

  /**
   * Registra o botão acultaFluentEmoji na barra de ferramentas.
   */
  class FluentEmoji extends Plugin {

    static get pluginName() {
      return 'FluentEmoji';
    }

    init() {
      const editor = this.editor;
      const config = editor.config.get('acultaFluentEmoji');

      if (!config || !config.catalog || config.catalog.length === 0) {
        return;
      }

      editor.ui.componentFactory.add('acultaFluentEmoji', (locale) => {
        const dropdown = createDropdown(locale);
        const labels = config.labels || {};

        dropdown.buttonView.set({
          label: labels.button || 'Emoji',
          tooltip: true,
          icon: EMOJI_ICON,
        });

        const panel = new EmojiPanelView(locale, config, {
          insert: (glyph) => {
            editor.execute('insertText', { text: glyph });
            dropdown.isOpen = false;
            editor.editing.view.focus();
          },
          close: () => {
            dropdown.isOpen = false;
            editor.editing.view.focus();
          },
        });

        dropdown.panelView.children.add(panel);

        this.listenTo(dropdown, 'change:isOpen', (event, name, isOpen) => {
          if (isOpen) {
            panel.focus();
          }
        });

        return dropdown;
      });
    }

  }

  window.CKEditor5 = window.CKEditor5 || {};
  window.CKEditor5.acultaFluentEmoji = { FluentEmoji };

})(window.CKEditor5);

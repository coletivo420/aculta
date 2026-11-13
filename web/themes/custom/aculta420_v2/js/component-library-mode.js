/**
 * @file
 * Seletor Claro/Escuro da UI Library. Controla só as pré-visualizações:
 * define data-theme no contêiner de cada story, nunca no <html>.
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'aculta-library-color-mode';
  const MODES = { light: 'Claro', dark: 'Escuro' };

  function readMode() {
    try {
      const value = window.localStorage.getItem(STORAGE_KEY);
      return value === 'dark' || value === 'light' ? value : 'light';
    }
    catch {
      return 'light';
    }
  }

  function saveMode(mode) {
    try {
      window.localStorage.setItem(STORAGE_KEY, mode);
    }
    catch {
      // Sem armazenamento: a escolha vale só para a página atual.
    }
  }

  function applyMode(mode) {
    // Página inteira da biblioteca: o contêiner principal recebe o modo, e
    // todo o conteúdo herda os tokens (texto, superfícies, bordas e componentes).
    const page = document.querySelector('.layout-page__content') || document.querySelector('main');
    if (page) {
      page.setAttribute('data-theme', mode);
    }
    // Pré-visualizações de componente: overview (__preview) e página única (__story).
    const previews = document.querySelectorAll('.ui_patterns_story__preview, .ui_patterns_story__story');
    Array.prototype.forEach.call(previews, function (node) {
      node.setAttribute('data-theme', mode);
    });
    // Prévias já montadas em Shadow DOM (carregamento em lote): atualiza o contêiner interno de cada uma.
    Array.prototype.forEach.call(document.querySelectorAll('.aculta-preview-host'), function (host) {
      const root = host.shadowRoot;
      const wrap = root && root.querySelector('.aculta-preview-host__content');
      if (wrap) {
        wrap.setAttribute('data-theme', mode);
      }
    });
  }

  function buildSelector(initial) {
    const group = document.createElement('div');
    group.className = 'aculta-library-mode';
    group.setAttribute('role', 'radiogroup');
    group.setAttribute('aria-label', 'Modo de cor das pré-visualizações');

    Object.keys(MODES).forEach(function (mode) {
      const label = document.createElement('label');
      label.className = 'aculta-library-mode__option';

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'aculta-library-mode';
      input.value = mode;
      input.checked = mode === initial;

      const text = document.createElement('span');
      text.textContent = MODES[mode];

      label.appendChild(input);
      label.appendChild(text);
      group.appendChild(label);

      input.addEventListener('change', function () {
        if (input.checked) {
          saveMode(mode);
          applyMode(mode);
        }
      });
    });

    return group;
  }

  function init() {
    const anchor = document.querySelector('.layout-page__content') || document.querySelector('main');
    if (!anchor || anchor.querySelector('.aculta-library-mode')) {
      return;
    }
    const mode = readMode();
    anchor.insertBefore(buildSelector(mode), anchor.firstChild);
    applyMode(mode);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  }
  else {
    init();
  }
})();

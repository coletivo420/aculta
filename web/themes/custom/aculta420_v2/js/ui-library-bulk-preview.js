/**
 * @file
 * Prévias da biblioteca de componentes em Shadow DOM.
 *
 * Cada prévia é buscada na página do componente e montada num shadow root próprio, com as folhas de
 * estilo do componente: o CSS de um item não vaza para os outros. Um carregador único atende o clique
 * individual e o botão "Exibir todas as prévias" (lote, com concorrência limitada).
 */
(function (Drupal, once) {
  'use strict';

  const CONCURRENCY_LIMIT = 2;

  const state = {
    isRunning: false,
    isCancelled: false,
    active: 0,
    queue: [],
    errors: new Set(),
    btn: null,
    btnText: null,
    status: null,
  };

  /**
   * Seções da biblioteca visíveis (fora de grupos ocultos e da busca).
   */
  function visibleSections() {
    return Array.from(document.querySelectorAll('.ui_patterns_component'))
      .filter((section) => section.querySelector('.ui_patterns_component__stories-container') && !section.closest('[hidden]'));
  }

  /**
   * Liga os diálogos do tema (componente Diálogo) dentro do shadow root: o script do tema procura o
   * diálogo pelo documento, que não enxerga o conteúdo do shadow root.
   */
  function bindDialogs(wrap) {
    wrap.querySelectorAll('dialog').forEach((dialog) => {
      dialog.querySelectorAll('[data-aculta-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
      });
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
          dialog.close();
        }
      });
    });
    wrap.querySelectorAll('[data-aculta-dialog-trigger]').forEach((trigger) => {
      const id = trigger.getAttribute('aria-controls');
      const dialog = id ? wrap.querySelector(`dialog[id="${CSS.escape(id)}"]`) : null;
      if (!dialog) {
        return;
      }
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        dialog.showModal();
        trigger.setAttribute('aria-expanded', 'true');
      });
      dialog.addEventListener('close', () => trigger.setAttribute('aria-expanded', 'false'));
    });
  }

  /**
   * Monta a prévia de uma seção dentro de um shadow root.
   *
   * @return {Promise<boolean>} true quando carregou.
   */
  function loadPreview(section) {
    if (section.dataset.acultaLoaded === '1') {
      return Promise.resolve(true);
    }
    const placeholder = section.querySelector('.aculta-preview-placeholder');
    const link = section.querySelector('.aculta-load-preview-btn');
    if (!placeholder || !link) {
      return Promise.resolve(false);
    }
    placeholder.classList.add('is-loading');

    return fetch(link.getAttribute('href'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((response) => {
        if (!response.ok) {
          throw new Error(String(response.status));
        }
        return response.text();
      })
      .then((html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const fragment = doc.querySelector('#component-stories-wrapper');
        if (!fragment) {
          throw new Error('fragment');
        }

        const host = document.createElement('div');
        host.className = 'aculta-preview-host';
        host.dataset.componentId = section.dataset.componentId || '';
        const root = host.attachShadow({ mode: 'open' });

        // Folhas de estilo e estilos da página do componente, isolados no shadow root.
        doc.querySelectorAll('link[rel="stylesheet"][href], style').forEach((node) => {
          root.appendChild(document.importNode(node, true));
        });

        // Tema: as variáveis CSS herdam para dentro do shadow root; o atributo de tema vai para o wrapper.
        const wrap = document.createElement('div');
        wrap.className = 'aculta-preview-host__content';
        // Modo da biblioteca (seletor Claro/Escuro): o ancestral mais próximo com data-theme, não o <html>.
        wrap.setAttribute('data-theme', section.closest('[data-theme]')?.getAttribute('data-theme') || 'light');
        wrap.appendChild(document.importNode(fragment, true));
        // O título do card da biblioteca já mostra o nome do componente: o título grande do story é removido aqui.
        wrap.querySelectorAll('.ui_patterns_story__name').forEach((title) => title.remove());
        root.appendChild(wrap);

        placeholder.replaceWith(host);
        section.dataset.acultaLoaded = '1';

        bindDialogs(wrap);
        Drupal.attachBehaviors(wrap);
        if (window.htmx && window.htmx.process) {
          window.htmx.process(wrap);
        }
        return true;
      })
      .catch(() => {
        placeholder.classList.remove('is-loading');
        placeholder.classList.add('is-error');
        return false;
      });
  }

  /**
   * Atualiza o botão do lote e o texto de status com os contadores da seleção visível.
   */
  function updateUI() {
    const { btn, status } = state;
    if (!btn || !status) {
      return;
    }
    const btnText = state.btnText;
    const sections = visibleSections();
    const total = sections.length;
    const loaded = sections.filter((s) => s.dataset.acultaLoaded === '1').length;
    const errors = sections.filter((s) => state.errors.has(s.id)).length;
    const completed = loaded + errors;

    if (state.isRunning) {
      btn.disabled = false;
      btn.setAttribute('aria-pressed', 'true');
      btn.setAttribute('data-state', 'loading');
      if (btnText) {
        btnText.textContent = Drupal.t('Cancelar carregamento');
      }
      let msg = Drupal.t('Carregando prévias: @current de @total', { '@current': loaded, '@total': total });
      if (errors > 0) {
        msg += Drupal.t(' (@errors com erro)', { '@errors': errors });
      }
      status.textContent = msg;
      return;
    }

    btn.setAttribute('aria-pressed', 'false');
    btn.disabled = false;

    if (state.isCancelled) {
      btn.setAttribute('data-state', 'cancelled');
      if (btnText) {
        btnText.textContent = Drupal.t('Continuar carregamento');
      }
      status.textContent = Drupal.t('Carregamento cancelado: @loaded de @total carregadas (@remaining restantes)', {
        '@loaded': loaded,
        '@total': total,
        '@remaining': total - completed,
      });
    }
    else if (completed >= total && total > 0) {
      btn.setAttribute('data-state', 'finished');
      if (errors > 0) {
        if (btnText) {
          btnText.textContent = Drupal.t('Tentar itens com erro (@count)', { '@count': errors });
        }
        status.textContent = Drupal.t('Concluído: @loaded carregadas, @errors com erro.', { '@loaded': loaded, '@errors': errors });
      }
      else {
        btn.disabled = true;
        if (btnText) {
          btnText.textContent = Drupal.t('Todas as prévias carregadas');
        }
        status.textContent = Drupal.t('Todas as @total prévias carregadas com sucesso.', { '@total': total });
      }
    }
    else {
      btn.setAttribute('data-state', 'idle');
      if (btnText) {
        btnText.textContent = Drupal.t('Exibir todas as prévias');
      }
      status.textContent = loaded > 0 ? Drupal.t('@loaded de @total prévias carregadas.', { '@loaded': loaded, '@total': total }) : '';
    }
  }

  /**
   * Preenche a fila com as seções visíveis que ainda não carregaram (ou só as com erro, na retentativa).
   */
  function populateQueue(onlyErrors) {
    state.queue = visibleSections().filter((section) => (
      onlyErrors ? state.errors.has(section.id) : section.dataset.acultaLoaded !== '1'
    ));
  }

  /**
   * Processa a fila respeitando o limite de concorrência.
   */
  function processQueue() {
    if (!state.isRunning || state.isCancelled) {
      return;
    }
    if (state.queue.length === 0 && state.active === 0) {
      state.isRunning = false;
      updateUI();
      return;
    }
    while (state.active < CONCURRENCY_LIMIT && state.queue.length > 0) {
      const section = state.queue.shift();
      state.active += 1;
      loadPreview(section).then((ok) => {
        if (ok) {
          state.errors.delete(section.id);
        }
        else {
          state.errors.add(section.id);
        }
      }).finally(() => {
        state.active -= 1;
        updateUI();
        processQueue();
      });
    }
  }

  /**
   * Troca de aba ou de filtro: interrompe a fila em andamento e atualiza o botão para o novo escopo.
   */
  function onScopeChanged() {
    const wasRunning = state.isRunning;
    state.isRunning = false;
    state.isCancelled = wasRunning;
    state.queue = [];
    updateUI();
  }

  // Durante o lote, links e formulários da grade não navegam a página (inclusive dentro dos shadow roots).
  document.addEventListener('click', (event) => {
    if (!state.isRunning) {
      return;
    }
    const path = event.composedPath ? event.composedPath() : [];
    const link = path.find((node) => node.tagName === 'A');
    if (link && path.some((node) => node.id === 'aculta-components-container') && !link.classList.contains('aculta-load-preview-btn')) {
      event.preventDefault();
    }
  }, true);

  document.addEventListener('submit', (event) => {
    if (!state.isRunning) {
      return;
    }
    const path = event.composedPath ? event.composedPath() : [];
    if (path.some((node) => node.id === 'aculta-components-container')) {
      event.preventDefault();
    }
  }, true);

  Drupal.behaviors.acultaUiLibraryBulkPreview = {
    attach(context) {
      // Clique individual: carrega só a prévia daquela seção.
      once('aculta-single-preview', '.aculta-load-preview-btn', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          event.preventDefault();
          const section = link.closest('.ui_patterns_component');
          if (!section) {
            return;
          }
          loadPreview(section).then((ok) => {
            if (ok) {
              state.errors.delete(section.id);
            }
            else {
              state.errors.add(section.id);
            }
            updateUI();
          });
        });
      });

      // Botão do lote.
      once('aculta-bulk-preview-init', '#aculta-btn-bulk-preview', context).forEach((btn) => {
        state.btn = btn;
        state.btnText = btn.querySelector('.aculta-bulk-preview-btn-text') || btn.querySelector('span:not(.aculta-icon)') || btn;
        state.status = document.getElementById('aculta-bulk-preview-status');
        document.addEventListener('aculta:ui-library-scope-changed', onScopeChanged);
        updateUI();

        btn.addEventListener('click', (event) => {
          event.preventDefault();
          if (state.isRunning) {
            state.isRunning = false;
            state.isCancelled = true;
            state.queue = [];
            updateUI();
            return;
          }
          state.isCancelled = false;
          state.isRunning = true;
          const retryErrors = btn.getAttribute('data-state') === 'finished' && state.errors.size > 0;
          populateQueue(retryErrors);
          updateUI();
          processQueue();
        });
      });
    },
  };
})(Drupal, once);

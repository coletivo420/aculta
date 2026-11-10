/**
 * @file
 * Gerenciador de carregamento em lote de prévias ("Exibir todas as prévias").
 *
 * Coordena o carregamento progressivo assíncrono das prévias sob demanda
 * da UI Patterns Library, respeitando um limite de concorrência configurável (padrão: 2).
 * Utiliza o HTMX nativo (htmx.ajax / data-hx-*) e apoia-se em Drupal.behaviors e core/once.
 */
(function (Drupal, once) {
  'use strict';

  // Configuração
  const CONCURRENCY_LIMIT = 2;

  // Estado da fila única no cliente
  const state = {
    isRunning: false,
    isCancelled: false,
    activeRequests: 0,
    queue: [],
    loadedSet: new Set(),
    errorSet: new Set(),
    totalCount: 0,
    domElements: {
      btnText: null,
      status: null,
      btn: null,
    },
  };

  /**
   * Atualiza a interface (rótulo do botão e texto de status acessível).
   */
  function updateUI() {
    const { btnText, status, btn } = state.domElements;
    if (!btn || !status) {
      return;
    }

    const total = state.totalCount;
    const loaded = state.loadedSet.size;
    const errors = state.errorSet.size;
    const completed = loaded + errors;

    if (state.isRunning) {
      btn.setAttribute('aria-pressed', 'true');
      btn.setAttribute('data-state', 'loading');
      if (btnText) {
        btnText.textContent = Drupal.t('Cancelar carregamento');
      }

      let statusMsg = Drupal.t('Carregando prévias: @current de @total', {
        '@current': loaded,
        '@total': total,
      });
      if (errors > 0) {
        statusMsg += Drupal.t(' (@errors com erro)', { '@errors': errors });
      }
      status.textContent = statusMsg;
    }
    else {
      btn.setAttribute('aria-pressed', 'false');

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
          status.textContent = Drupal.t('Concluído: @loaded carregadas, @errors com erro.', {
            '@loaded': loaded,
            '@errors': errors,
          });
        }
        else {
          btn.disabled = true;
          if (btnText) {
            btnText.textContent = Drupal.t('Todas as prévias carregadas');
          }
          status.textContent = Drupal.t('Todas as @total prévias carregadas com sucesso.', {
            '@total': total,
          });
        }
      }
      else {
        btn.setAttribute('data-state', 'idle');
        if (btnText) {
          btnText.textContent = Drupal.t('Exibir todas as prévias');
        }
        if (loaded > 0) {
          status.textContent = Drupal.t('@loaded de @total prévias carregadas.', {
            '@loaded': loaded,
            '@total': total,
          });
        }
        else {
          status.textContent = '';
        }
      }
    }
  }

  /**
   * Processa os próximos itens da fila enquanto houver slots de concorrência.
   */
  function processQueue() {
    if (!state.isRunning || state.isCancelled) {
      return;
    }

    // Verifica se terminou
    const completed = state.loadedSet.size + state.errorSet.size;
    if (state.queue.length === 0 && state.activeRequests === 0) {
      state.isRunning = false;
      updateUI();
      return;
    }

    while (state.activeRequests < CONCURRENCY_LIMIT && state.queue.length > 0) {
      const item = state.queue.shift();
      const componentId = item.componentId;

      // Se já foi carregado por clique individual no meio do processo, pula
      if (state.loadedSet.has(componentId)) {
        continue;
      }

      loadComponentPreview(item);
    }
  }

  /**
   * Carrega a prévia de um componente individual utilizando HTMX.
   */
  function loadComponentPreview(item) {
    const { componentId, url, targetSelector, selectSelector, indicatorSelector } = item;

    const targetEl = document.querySelector(targetSelector);
    if (!targetEl) {
      // Se o target não existe mais no DOM, provavelmente já foi substituído
      state.loadedSet.add(componentId);
      updateUI();
      processQueue();
      return;
    }

    state.activeRequests++;
    const indicatorEl = indicatorSelector ? document.querySelector(indicatorSelector) : null;
    if (indicatorEl) {
      indicatorEl.classList.add('htmx-request');
    }

    let settled = false;
    const onComplete = (success) => {
      if (settled) {
        return;
      }
      settled = true;
      state.activeRequests--;
      if (indicatorEl) {
        indicatorEl.classList.remove('htmx-request');
      }

      if (success) {
        state.loadedSet.add(componentId);
        state.errorSet.delete(componentId);
      }
      else {
        state.errorSet.add(componentId);
      }

      updateUI();
      // Prossegue a fila sequencialmente
      processQueue();
    };

    // Procura o botão HTMX nativo para disparo padrão ou usa fetch sob fallback
    const singleBtn = targetEl.querySelector('.aculta-load-preview-btn');
    if (singleBtn && window.htmx && window.htmx.trigger) {
      // Escuta o término do ciclo HTMX especificamente neste target
      const onAfterSwap = (evt) => {
        const swapTarget = evt.detail ? evt.detail.target : evt.target;
        if (swapTarget && (swapTarget === targetEl || targetEl.contains(swapTarget) || swapTarget.closest(`#preview-container-${componentId}`))) {
          document.body.removeEventListener('htmx:afterSwap', onAfterSwap);
          document.body.removeEventListener('htmx:responseError', onError);
          document.body.removeEventListener('htmx:sendError', onError);
          onComplete(true);
        }
      };

      const onError = (evt) => {
        const errElt = evt.detail ? (evt.detail.elt || evt.detail.target) : evt.target;
        if (errElt && (errElt === singleBtn || singleBtn.contains(errElt))) {
          document.body.removeEventListener('htmx:afterSwap', onAfterSwap);
          document.body.removeEventListener('htmx:responseError', onError);
          document.body.removeEventListener('htmx:sendError', onError);
          onComplete(false);
        }
      };

      document.body.addEventListener('htmx:afterSwap', onAfterSwap);
      document.body.addEventListener('htmx:responseError', onError);
      document.body.addEventListener('htmx:sendError', onError);

      // Timeout de segurança caso o evento htmx não dispare
      setTimeout(() => {
        if (!settled) {
          document.body.removeEventListener('htmx:afterSwap', onAfterSwap);
          document.body.removeEventListener('htmx:responseError', onError);
          document.body.removeEventListener('htmx:sendError', onError);
          const loadedContainer = document.querySelector(`#preview-container-${componentId}`);
          const storiesWrapper = loadedContainer ? loadedContainer.querySelector(selectSelector) : null;
          onComplete(storiesWrapper !== null || !document.querySelector(targetSelector));
        }
      }, 10000);

      // Dispara o clique no botão HTMX para acionar o ciclo completo do framework
      singleBtn.click();
    }
    else {
      // Fallback com fetch direto
      fetch(url, {
        headers: {
          'HX-Request': 'true',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
      .then((res) => {
        if (!res.ok) {
          throw new Error('Network error');
        }
        return res.text();
      })
      .then((html) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const fragment = doc.querySelector(selectSelector);
        if (fragment && targetEl.parentNode) {
          targetEl.parentNode.replaceChild(fragment, targetEl);
          Drupal.attachBehaviors(fragment);
          onComplete(true);
        }
        else {
          onComplete(false);
        }
      })
      .catch(() => {
        onComplete(false);
      });
    }
  }

  /**
   * Constrói a lista de componentes pendentes inspecionando o DOM.
   */
  function populateQueue(onlyErrors = false) {
    const buttons = Array.from(document.querySelectorAll('.aculta-load-preview-btn'));
    state.totalCount = buttons.length + state.loadedSet.size;

    const newQueue = [];
    buttons.forEach((btn) => {
      const url = btn.getAttribute('data-hx-get') || btn.getAttribute('href');
      const targetSelector = btn.getAttribute('data-hx-target');
      const selectSelector = btn.getAttribute('data-hx-select') || '#component-stories-wrapper';
      const indicatorSelector = btn.getAttribute('data-hx-indicator');
      const placeholder = btn.closest('.aculta-preview-placeholder');
      const section = btn.closest('.ui_patterns_component');
      const componentId = section ? section.id : (placeholder ? placeholder.id.replace('placeholder-', '') : null);

      if (!componentId || !url || !targetSelector) {
        return;
      }

      if (onlyErrors) {
        if (state.errorSet.has(componentId)) {
          newQueue.push({ componentId, url, targetSelector, selectSelector, indicatorSelector });
        }
      }
      else {
        if (!state.loadedSet.has(componentId)) {
          newQueue.push({ componentId, url, targetSelector, selectSelector, indicatorSelector });
        }
      }
    });

    state.queue = newQueue;
  }

  Drupal.behaviors.acultaUiLibraryBulkPreview = {
    attach(context) {
      // 1. Escuta cliques em botões individuais para atualizar a contagem e conjuntos
      once('aculta-single-preview-watcher', '.aculta-load-preview-btn', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const section = btn.closest('.ui_patterns_component');
          const componentId = section ? section.id : null;
          if (componentId) {
            // Marca como em processamento ou assiste o swap
            const onSwap = (evt) => {
              if (evt.target && evt.target.matches && (evt.target.matches(`#preview-container-${componentId}`) || evt.target.closest(`#preview-container-${componentId}`))) {
                state.loadedSet.add(componentId);
                state.errorSet.delete(componentId);
                document.body.removeEventListener('htmx:afterSwap', onSwap);
                updateUI();
              }
            };
            document.body.addEventListener('htmx:afterSwap', onSwap);
          }
        });
      });

      // 2. Inicializa o botão global "Exibir todas as prévias"
      once('aculta-bulk-preview-init', '#aculta-btn-bulk-preview', context).forEach((btn) => {
        state.domElements.btn = btn;
        state.domElements.btnText = btn.querySelector('.aculta-bulk-preview-btn-text') || btn.querySelector('span') || btn;
        state.domElements.status = document.getElementById('aculta-bulk-preview-status');

        // Contagem inicial de componentes
        const initialButtons = document.querySelectorAll('.aculta-load-preview-btn');
        state.totalCount = initialButtons.length;

        btn.addEventListener('click', (e) => {
          e.preventDefault();

          // Ação: Cancelar se já estiver rodando
          if (state.isRunning) {
            state.isRunning = false;
            state.isCancelled = true;
            state.queue = [];
            updateUI();
            return;
          }

          // Ação: Iniciar ou Continuar
          state.isCancelled = false;
          state.isRunning = true;

          // Se estava finalizado com erros, tenta apenas os erros
          const isRetryErrors = (btn.getAttribute('data-state') === 'finished' && state.errorSet.size > 0);
          populateQueue(isRetryErrors);

          updateUI();
          processQueue();
        });
      });
    },
  };

})(Drupal, once);

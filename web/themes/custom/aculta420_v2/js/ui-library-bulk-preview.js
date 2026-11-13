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
    domElements: {
      btnText: null,
      status: null,
      btn: null,
    },
  };

  /**
   * Obtém o elemento de texto dentro do botão (garantindo que não seja o ícone).
   */
  function getButtonTextElement(btn) {
    if (!btn) {
      return null;
    }
    // Procura por classe específica ou por span não-ícone
    const explicitText = btn.querySelector('.aculta-bulk-preview-btn-text');
    if (explicitText) {
      return explicitText;
    }
    const spans = Array.from(btn.querySelectorAll('span'));
    const textSpan = spans.find((s) => !s.classList.contains('aculta-icon') && !s.closest('.aculta-icon'));
    if (textSpan) {
      return textSpan;
    }
    // Se o próprio botão não tem spans separados, retorna o botão
    return btn;
  }

  /**
   * Botões de prévia do escopo atual: apenas os que estão visíveis,
   * ou seja, da aba selecionada e que passam pelo filtro de busca.
   */
  function getScopeItems() {
    return Array.from(document.querySelectorAll('.aculta-load-preview-btn'))
      .filter((btn) => !btn.closest('[hidden]'))
      .map((btn) => {
        const url = btn.getAttribute('data-hx-get') || btn.getAttribute('href');
        const targetSelector = btn.getAttribute('data-hx-target');
        const selectSelector = btn.getAttribute('data-hx-select') || '#component-stories-wrapper';
        const indicatorSelector = btn.getAttribute('data-hx-indicator');
        const placeholder = btn.closest('.aculta-preview-placeholder');
        const section = btn.closest('.ui_patterns_component');
        const componentId = section ? section.id : (placeholder ? placeholder.id.replace('placeholder-', '') : null);
        return { componentId, url, targetSelector, selectSelector, indicatorSelector };
      })
      .filter((item) => item.componentId && item.url && item.targetSelector);
  }

  /**
   * IDs de todos os componentes com stories no escopo, inclusive os que já
   * trocaram o botão pela prévia renderizada.
   */
  function getScopeIds() {
    return Array.from(document.querySelectorAll('.ui_patterns_component'))
      .filter((section) => !section.closest('[hidden]'))
      .filter((section) => section.querySelector('.ui_patterns_component__stories-container'))
      .map((section) => section.id)
      .filter(Boolean);
  }

  /**
   * Atualiza a interface (rótulo do botão e texto de status acessível).
   * Contadores consideram somente o escopo visível.
   */
  function updateUI() {
    const { status, btn } = state.domElements;
    if (!btn || !status) {
      return;
    }

    const btnText = getButtonTextElement(btn);
    const scopeIds = getScopeIds();
    const total = scopeIds.length;
    const loaded = scopeIds.filter((id) => state.loadedSet.has(id)).length;
    const errors = scopeIds.filter((id) => state.errorSet.has(id)).length;
    const completed = loaded + errors;

    if (state.isRunning) {
      btn.disabled = false;
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

    // Carrega apenas o fragmento selecionado. Simular o clique HTMX no link
    // pode aplicar HX-Push-Url e retirar o administrador do catálogo durante
    // o lote; fetch mantém a rota atual.
    fetch(url, {
      headers: {
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
          // A story inclui o fallback <noscript> do BigPipe dentro do wrapper.
          // Ao inserir o fragmento num documento com JS ativo, o meta refresh
          // redireciona o catálogo para a página individual da story.
          fragment.querySelectorAll('noscript').forEach((fallback) => fallback.remove());
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

  /**
   * Constrói a fila com as prévias do escopo visível (aba selecionada).
   * Em modo de retentativa, inclui apenas os itens com erro.
   */
  function populateQueue(onlyErrors = false) {
    state.queue = getScopeItems().filter((item) => (
      onlyErrors
        ? state.errorSet.has(item.componentId)
        : !state.loadedSet.has(item.componentId)
    ));
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
        state.domElements.btnText = getButtonTextElement(btn);
        state.domElements.status = document.getElementById('aculta-bulk-preview-status');

        document.addEventListener('aculta:ui-library-scope-changed', onScopeChanged);
        updateUI();

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
          const isRetryErrors = (btn.getAttribute('data-state') === 'finished'
            && getScopeItems().some((item) => state.errorSet.has(item.componentId)));
          populateQueue(isRetryErrors);

          updateUI();
          processQueue();
        });
      });
    },
  };

})(Drupal, once);

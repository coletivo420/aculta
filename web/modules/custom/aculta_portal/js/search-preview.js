/**
 * Prévia da busca no popup: espera 300 ms sem digitação e carrega o HTML da prévia da rota do Portal (mesmo host).
 * Só a resposta mais recente é exibida; falha mostra uma mensagem na própria caixa.
 */
(function (Drupal, once) {
  'use strict';

  const DELAY_MS = 300;
  const TABBABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const visibleTabbables = (container) =>
    Array.from(container.querySelectorAll(TABBABLE)).filter((el) => el.offsetParent !== null || el === document.activeElement);

  // Botão × do diálogo: o jQuery UI não grava o closeText no DOM; o nome acessível é definido aqui.
  if (window.jQuery) {
    window.jQuery(document).on('dialog:aftercreate', (event, dialog, $element) => {
      if (!$element || !$element.find('#aculta-search-preview').length) {
        return;
      }
      const wrapper = $element.closest('.ui-dialog').get(0);
      const button = wrapper ? wrapper.querySelector('.ui-dialog-titlebar-close') : null;
      if (button) {
        button.setAttribute('aria-label', Drupal.t('Fechar'));
        button.setAttribute('title', Drupal.t('Fechar'));
      }
    });
  }

  Drupal.behaviors.acultaSearchPreview = {
    attach(context) {
      once('aculta-search-preview', 'input.aculta-search-preview-input', context).forEach((input) => {
        const form = input.form;
        const boxId = input.dataset.previewTarget || 'aculta-search-preview';
        const box = Array.from(form.querySelectorAll('[id]')).find((element) => element.id === boxId);
        const url = input.dataset.previewUrl;
        const minChars = Number.parseInt(input.dataset.minChars || '3', 10);
        // A dica inicial é guardada para voltar quando o termo fica curto demais.
        const hintHtml = box ? box.innerHTML : '';
        let timer = null;
        let controller = null;

        // Popup: o diálogo do Drupal foca o primeiro elemento; o foco deve ir ao campo de busca.
        setTimeout(() => {
          if (input.closest('.ui-dialog')) {
            input.focus();
          }
        }, 0);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        if (box) {
          input.setAttribute('aria-controls', box.id);
        }

        const showHint = () => {
          if (box) {
            box.innerHTML = hintHtml;
            box.removeAttribute('hidden');
          }
          input.setAttribute('aria-expanded', 'false');
        };

        const hidePreview = () => {
          if (box) {
            box.setAttribute('hidden', 'hidden');
          }
          input.setAttribute('aria-expanded', 'false');
        };

        input.addEventListener('input', () => {
          clearTimeout(timer);
          const term = input.value.trim();
          if (term.length === 0) {
            if (controller) {
              controller.abort();
              controller = null;
            }
            hidePreview();
            return;
          }
          if (box) {
            box.removeAttribute('hidden');
          }
          if (term.length < minChars) {
            // Termo curto: cancela a busca em andamento e não faz requisição.
            if (controller) {
              controller.abort();
              controller = null;
            }
            showHint();
            return;
          }
          if (box) {
            box.innerHTML = '';
          }
          timer = setTimeout(() => {
            if (controller) {
              controller.abort();
            }
            controller = new AbortController();
            const current = controller;
            fetch(`${url}?q=${encodeURIComponent(term)}`, {
              credentials: 'same-origin',
              cache: 'no-store',
              signal: current.signal,
            })
              .then((response) => {
                if (!response.ok) {
                  throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
              })
              .then((html) => {
                if (current === controller && box) {
                  box.innerHTML = html;
                  input.setAttribute('aria-expanded', box.querySelector('a') ? 'true' : 'false');
                }
              })
              .catch((error) => {
                if (error.name === 'AbortError') {
                  return;
                }
                if (current === controller && box) {
                  box.textContent = Drupal.t('Não foi possível atualizar a prévia agora.');
                }
              });
          }, DELAY_MS);
        });

        // Setas: ↓ no campo entra na lista; ↑/↓ percorrem os resultados; ↑ no primeiro volta ao campo.
        input.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowDown' && box) {
            const first = box.querySelector('a');
            if (first) {
              event.preventDefault();
              first.focus();
            }
          }
        });
        if (box) {
          box.addEventListener('keydown', (event) => {
            const links = Array.from(box.querySelectorAll('a'));
            const index = links.indexOf(document.activeElement);
            if (index === -1) {
              return;
            }
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              links[Math.min(index + 1, links.length - 1)].focus();
            }
            else if (event.key === 'ArrowUp') {
              event.preventDefault();
              if (index === 0) {
                input.focus();
              }
              else {
                links[index - 1].focus();
              }
            }
          });
        }

        // Diálogo: Tab e Shift+Tab fazem ciclo dentro do popup, nos dois extremos.
        // Fase de captura: roda antes do jQuery UI, e o diálogo é procurado a cada tecla.
        const trapTab = (event) => {
          if (!input.isConnected) {
            document.removeEventListener('keydown', trapTab, true);
            return;
          }
          const dialogEl = input.closest('.ui-dialog');
          if (event.key !== 'Tab' || !dialogEl) {
            return;
          }
          const items = visibleTabbables(dialogEl);
          if (items.length === 0) {
            return;
          }
          const first = items[0];
          const last = items[items.length - 1];
          const active = document.activeElement;
          if (!dialogEl.contains(active)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            (event.shiftKey ? last : first).focus();
          }
          else if (event.shiftKey && active === first) {
            event.preventDefault();
            event.stopImmediatePropagation();
            last.focus();
          }
          else if (!event.shiftKey && active === last) {
            event.preventDefault();
            event.stopImmediatePropagation();
            first.focus();
          }
        };
        document.addEventListener('keydown', trapTab, true);
      });
    },
  };
})(Drupal, once);

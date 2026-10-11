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

  Drupal.behaviors.acultaSearchPreview = {
    attach(context) {
      once('aculta-search-preview', 'input.aculta-search-preview-input', context).forEach((input) => {
        const form = input.form;
        const box = form.querySelector('#aculta-search-preview');
        const url = input.dataset.previewUrl;
        const minChars = Number.parseInt(input.dataset.minChars || '3', 10);
        // A dica inicial é guardada para voltar quando o termo fica curto demais.
        const hintHtml = box ? box.innerHTML : '';
        const dialog = input.closest('.ui-dialog');
        let timer = null;
        let controller = null;

        // Popup: o diálogo do Drupal foca o primeiro elemento; o foco deve ir ao campo de busca.
        if (dialog) {
          setTimeout(() => input.focus(), 0);
        }

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        if (box) {
          input.setAttribute('aria-controls', box.id);
        }

        const showHint = () => {
          if (box) {
            box.innerHTML = hintHtml;
          }
          input.setAttribute('aria-expanded', 'false');
        };

        input.addEventListener('input', () => {
          clearTimeout(timer);
          const term = input.value.trim();
          if (term.length < minChars) {
            // Termo curto: cancela a busca em andamento e não faz requisição.
            if (controller) {
              controller.abort();
              controller = null;
            }
            showHint();
            return;
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
        if (dialog) {
          dialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
              return;
            }
            const items = visibleTabbables(dialog);
            if (items.length === 0) {
              return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
              event.preventDefault();
              last.focus();
            }
            else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
              event.preventDefault();
              first.focus();
            }
          });
        }
      });
    },
  };
})(Drupal, once);

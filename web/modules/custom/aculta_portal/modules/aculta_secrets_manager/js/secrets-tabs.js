/**
 * Abas de ambiente do painel de credenciais.
 *
 * Só alterna a visibilidade dos painéis. Não faz requisição e não altera o ambiente operacional:
 * ele vem da configuração confiável do servidor.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaSecretsTabs = {
    attach(context) {
      once('aculta-secrets-tabs', '[data-portal-ui="secrets-tabs"]', context).forEach((root) => {
        const tabs = Array.from(root.querySelectorAll('[role="tab"]'));
        if (tabs.length === 0) {
          return;
        }

        const select = (tab, moveFocus) => {
          tabs.forEach((item) => {
            const selected = item === tab;
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.tabIndex = selected ? 0 : -1;
            const panel = document.getElementById(item.getAttribute('aria-controls'));
            if (panel) {
              panel.hidden = !selected;
            }
          });
          if (moveFocus) {
            tab.focus();
          }
        };

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => select(tab, false));
          tab.addEventListener('keydown', (event) => {
            let next = null;
            if (event.key === 'ArrowRight') {
              next = tabs[(index + 1) % tabs.length];
            }
            else if (event.key === 'ArrowLeft') {
              next = tabs[(index - 1 + tabs.length) % tabs.length];
            }
            else if (event.key === 'Home') {
              next = tabs[0];
            }
            else if (event.key === 'End') {
              next = tabs[tabs.length - 1];
            }
            if (next) {
              event.preventDefault();
              select(next, true);
            }
          });
        });

        const initial = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];
        select(initial, false);
      });
    },
  };

})(Drupal, once);

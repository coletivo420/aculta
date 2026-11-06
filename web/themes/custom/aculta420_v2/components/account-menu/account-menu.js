((Drupal, once) => {
  // Painel de tela cheia: Escape e X fecham, o foco volta ao gatilho e fica preso no painel enquanto aberto.
  // Sem JavaScript, o <details> continua abrindo e fechando pelo gatilho.
  Drupal.behaviors.acultaAccountMenu = {
    attach(context) {
      once('aculta-account-menu', '.aculta-account-menu__details', context).forEach((details) => {
        const summary = details.querySelector('.aculta-account-menu__summary');
        const panel = details.querySelector('.aculta-account-menu__panel');
        const closeButton = details.querySelector('[data-aculta-account-menu-close]');
        if (!summary || !panel) return;

        const focusables = () => Array.from(
          panel.querySelectorAll('a[href], button:not([disabled])'),
        ).filter((element) => element.offsetParent !== null || element === closeButton);

        const close = () => {
          if (!details.open) return;
          details.open = false;
          summary.focus();
        };

        // aria-expanded explícito no gatilho, além do estado nativo do <details>.
        const syncExpanded = () => summary.setAttribute('aria-expanded', String(details.open));
        syncExpanded();

        details.addEventListener('toggle', () => {
          syncExpanded();
          if (!details.open) return;
          const items = focusables();
          (closeButton || items[0])?.focus();
        });

        closeButton?.addEventListener('click', close);

        details.addEventListener('keydown', (event) => {
          if (!details.open) return;

          if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
          }

          if (event.key !== 'Tab') return;

          const items = focusables();
          if (items.length === 0) return;
          const first = items[0];
          const last = items[items.length - 1];
          const current = document.activeElement;

          if (event.shiftKey && (current === first || !panel.contains(current))) {
            event.preventDefault();
            last.focus();
          }
          else if (!event.shiftKey && (current === last || !panel.contains(current))) {
            event.preventDefault();
            first.focus();
          }
        });
      });
    },
  };
})(Drupal, once);

(function (Drupal, once) {
  'use strict';

  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  /**
   * Popup persistente de aceite dos termos.
   *
   * Esc e o botão de fechar do navegador podem fechar um <dialog> sem ativação do usuário, e nesse caso o
   * cancel não é cancelável. Por isso o popup é reaberto no evento close enquanto a página estiver aqui (o aceite
   * recarrega a página). O foco fica preso: Tab e Shift+Tab circulam dentro do popup. Sem suporte a <dialog>,
   * o popup não abre; o servidor não entrega o aceite sem os termos aceitos.
   */
  function focusables(dialog) {
    return Array.from(dialog.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null);
  }

  Drupal.behaviors.acultaAccountTermsModal = {
    attach(context) {
      once('aculta-account-terms-modal', '#aculta-account-terms', context).forEach((dialog) => {
        if (typeof dialog.showModal !== 'function') {
          return;
        }
        const open = () => {
          if (dialog.isConnected && !dialog.open) {
            dialog.showModal();
          }
        };

        dialog.addEventListener('cancel', (event) => event.preventDefault());
        dialog.addEventListener('close', () => window.setTimeout(open, 0));

        dialog.addEventListener('keydown', (event) => {
          if (event.key !== 'Tab') {
            return;
          }
          const items = focusables(dialog);
          if (items.length === 0) {
            return;
          }
          const first = items[0];
          const last = items[items.length - 1];
          const active = document.activeElement;
          const outside = !dialog.contains(active);
          if (event.shiftKey && (active === first || outside)) {
            event.preventDefault();
            last.focus();
          }
          else if (!event.shiftKey && (active === last || outside)) {
            event.preventDefault();
            first.focus();
          }
        });

        // Foco que sai do popup (por exemplo, pelo navegador) volta para o primeiro controle.
        document.addEventListener('focusin', (event) => {
          if (dialog.open && !dialog.contains(event.target)) {
            focusables(dialog)[0]?.focus();
          }
        });

        open();
        dialog.querySelector('input[type="checkbox"]')?.focus();
      });
    },
  };

})(Drupal, once);

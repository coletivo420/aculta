(function (Drupal, once) {
  'use strict';

  /**
   * Abre o aceite de termos como modal persistente.
   *
   * showModal() dá foco preso e torna o restante da página inerte. O evento cancel (Esc) é cancelado, e não há
   * botão de fechar nem fechamento por clique fora. Sem suporte a <dialog>, o popup não abre; o servidor continua
   * bloqueando as ações protegidas.
   */
  Drupal.behaviors.acultaAccountTermsModal = {
    attach(context) {
      once('aculta-account-terms-modal', '#aculta-account-terms', context).forEach((dialog) => {
        if (typeof dialog.showModal !== 'function') {
          return;
        }
        dialog.addEventListener('cancel', (event) => event.preventDefault());
        dialog.showModal();
        dialog.querySelector('input[type="checkbox"]')?.focus();
      });
    },
  };

})(Drupal, once);

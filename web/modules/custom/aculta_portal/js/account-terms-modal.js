(function (Drupal, once) {
  'use strict';

  /**
   * Popup persistente de aceite dos termos, como diálogo modal do Drupal (core/drupal.dialog).
   *
   * O diálogo não fecha sem aceite: Esc não fecha (closeOnEscape) e beforeClose cancela qualquer fechamento.
   * O X some pelo CSS do tema (data-portal-ui="account-terms"). O foco fica preso no diálogo (modal do jQuery UI).
   * Depois do aceite o formulário envia e a página volta ao destino. Sem JavaScript o conteúdo continua oculto
   * (atributo hidden) e o servidor não libera as áreas restritas.
   */
  Drupal.behaviors.acultaAccountTermsModal = {
    attach(context) {
      once('aculta-account-terms-modal', '#aculta-account-terms', context).forEach((element) => {
        element.removeAttribute('hidden');
        const dialog = Drupal.dialog(element, {
          title: element.dataset.dialogTitle || '',
          modal: true,
          closeOnEscape: false,
          width: 520,
          maxHeight: window.innerHeight - 32,
          // Centro da janela (desktop e celular); sem isso o diálogo cai para o padrão do Drupal, no fim da tela.
          position: { my: 'center', at: 'center', of: window, collision: 'fit' },
          beforeClose: () => false,
        });
        dialog.showModal();
        element.querySelector('input[type="checkbox"]')?.focus();
      });
    },
  };

})(Drupal, once);

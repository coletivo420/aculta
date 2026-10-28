(function (Drupal, once) {
  'use strict';

  /**
   * Etapa de criação de senha do cadastro simplificado como diálogo modal (core/drupal.dialog).
   *
   * Igual ao aceite dos termos: não fecha por Esc nem pelo X (o tema esconde o X) e beforeClose cancela qualquer
   * fechamento. Só o envio do formulário sai da tela, levando à página inicial. Sem JavaScript o formulário continua
   * oculto (atributo hidden) e a conta fica sem senha, o que é o comportamento esperado para quem não consegue abrir o modal.
   */
  Drupal.behaviors.acultaAccountPasswordModal = {
    attach(context) {
      once('aculta-account-password-modal', '#aculta-account-password', context).forEach((element) => {
        element.removeAttribute('hidden');
        const dialog = Drupal.dialog(element, {
          title: element.dataset.dialogTitle || Drupal.t('Crie sua senha'),
          modal: true,
          closeOnEscape: false,
          beforeClose: () => false,
        });
        dialog.showModal();
        // O overlay do jQuery UI é criado com o z-index do diálogo; baixa uma camada para o botão do modal continuar clicável.
        setTimeout(() => {
          document.querySelectorAll('.ui-widget-overlay').forEach((overlay) => {
            overlay.style.zIndex = '10001';
          });
        }, 0);
        element.querySelector('input[type="password"]')?.focus();
      });
    },
  };

})(Drupal, once);

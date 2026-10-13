/**
 * Modal da bio: o botão Limpar esvazia a caixa de texto. Só altera o campo; o texto só é salvo ao clicar em Salvar bio.
 */
(function (Drupal, once) {
  Drupal.behaviors.acultaAccountBioForm = {
    attach(context) {
      once('aculta-account-bio-clear', '[data-aculta-bio-clear]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          // O Drupal renderiza o botão como submit: o clique não pode enviar o formulário.
          event.preventDefault();
          const field = button.form ? button.form.querySelector('textarea[name="bio"]') : null;
          if (field) {
            field.value = '';
            field.focus();
          }
        });
      });
    },
  };
})(Drupal, once);

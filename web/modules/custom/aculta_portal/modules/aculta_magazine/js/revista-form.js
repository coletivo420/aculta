/**
 * Publicação da Revista: aviso antes de sair da página com alterações não salvas.
 *
 * O envio do formulário desliga o aviso, para que salvar, pré-visualizar e excluir não sejam bloqueados.
 * Usa Drupal.behaviors com once(), para não registrar o mesmo ouvinte duas vezes depois de uma troca AJAX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaMagazineRevistaForm = {
    attach(context) {
      once('aculta-magazine-revista-form', 'form[id^="node-revista-"]', context).forEach((form) => {
        let dirty = false;
        let submitting = false;
        form.addEventListener('input', () => { dirty = true; });
        form.addEventListener('change', () => { dirty = true; });
        form.addEventListener('submit', () => { submitting = true; });
        window.addEventListener('beforeunload', (event) => {
          if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
          }
        });
      });
    },
  };

})(Drupal, once);

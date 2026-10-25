/**
 * Publicação da Revista: aviso antes de sair da página com alterações não salvas.
 *
 * O envio do formulário desliga o aviso, para que salvar, pré-visualizar e excluir não sejam bloqueados.
 * Usa Drupal.behaviors com once(), para não registrar o mesmo ouvinte duas vezes depois de uma troca AJAX.
 * Quando o navegador restaura a página do cache de retorno (bfcache) depois de um envio concluído, o estado
 * de envio é limpo; sem isso, o aviso ficaria desligado para sempre. O retorno por "Voltar" a um formulário
 * não enviado preserva o aviso.
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
        window.addEventListener('pageshow', (event) => {
          if (event.persisted && submitting) {
            dirty = false;
            submitting = false;
          }
        });
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

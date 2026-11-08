/**
 * Citar comentário no fórum: preserva o fluxo seguro do servidor.
 *
 * COM-02/03/07/10:
 * A citação exige associação estrita do pai validado no servidor e a política
 * central de segurança de citação (ForumQuoteBuilder). Não intercepta cegamente
 * sem formulário validado para o pai/contexto, não confia em pid do DOM nem copia
 * innerText bruto alternativo. O fluxo padrão do servidor (comment.reply com quote
 * validado) constrói a entidade, o autor autorizado e a citação sanitizada.
 *
 * O agente 2 integrará o novo compositor inline em C02-05/06 com transporte Form API/HTMX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaForumQuote = {
    attach(context) {
      // Usa atributos semânticos em vez de literais frágeis de URL.
      once('aculta-forum-quote', 'a[data-action="comment-quote"], a[href*="?quote="]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          // Identifica o container de discussão específico (suporta múltiplos contextos na página).
          const threadContainer = link.closest('[data-aculta-discussion-thread], [data-aculta-discussion-context]');
          const quoteId = link.getAttribute('data-quote-id');

          // Procura formulário já construído e validado pelo servidor para esta discussão e este pai.
          let validatedForm = null;
          if (threadContainer && quoteId) {
            validatedForm = threadContainer.querySelector(`form[data-parent-id="${quoteId}"]`);
          }

          // Se não houver formulário já validado no servidor para o pai específico nesta discussão,
          // não intercepta: preserva o fluxo seguro do servidor via URL para evitar respostas com pai órfão ou incorreto.
          if (!validatedForm) {
            return;
          }

          // Fluxo com formulário já construído e validado pelo servidor:
          // Foca suavemente o formulário sem duplicar ou corromper conteúdo existente.
          validatedForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
          const instances = Drupal.CKEditor5Instances ? [...Drupal.CKEditor5Instances.values()] : [];
          const editor = instances.find((instance) => validatedForm.contains(instance.sourceElement));
          if (editor) {
            editor.editing.view.focus();
          }
          else {
            const textarea = validatedForm.querySelector('textarea');
            if (textarea) {
              textarea.focus();
            }
          }
        });
      });
    },
  };
})(Drupal, once);

/**
 * Responder e Citar em popup (SDC aculta420_v2:dialog) na discussão do fórum.
 *
 * Responder e Citar carregam o compositor por HTMX dentro do diálogo modal da página do
 * tópico (id aculta-reply-dialog). O diálogo usa o mesmo comportamento dos outros popups
 * do tema (utility-dialogs.js): centralizado, com fundo esmaecido, foco preso e fechamento
 * pelo botão, pelo fundo ou por Escape.
 *
 * Envio: o formulário troca o corpo do diálogo pelo próprio compositor. Se o compositor
 * voltar, houve erro de validação e ele continua aberto. Se não voltar, a postagem foi
 * publicada: o diálogo fecha e a página é recarregada, mostrando o novo comentário.
 *
 * Limpar esvazia o editor CKEditor 5 e os demais campos, sem depender do reset nativo.
 */
(function (Drupal, once) {
  'use strict';

  const dialogId = 'aculta-reply-dialog';
  const dialogBodyId = 'aculta-reply-dialog-body';
  const triggerSelector = '[data-action="comment-reply"], [data-action="comment-quote"]';

  function getDialog() {
    return document.getElementById(dialogId);
  }

  function getDialogBody() {
    return document.getElementById(dialogBodyId);
  }

  function editorFor(form) {
    const instances = Drupal.CKEditor5Instances ? [...Drupal.CKEditor5Instances.values()] : [];
    return instances.find((instance) => form.contains(instance.sourceElement)) || null;
  }

  function focusComposer(composer) {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    composer.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
    const editor = editorFor(composer);
    if (editor) {
      editor.editing.view.focus();
      return;
    }
    const textarea = composer.querySelector('textarea');
    if (textarea) {
      textarea.focus();
    }
  }

  Drupal.behaviors.acultaForumReplyPopup = {
    attach(context) {
      once('aculta-reply-popup', document.body, context).forEach((body) => {
        // Responder/Citar: o compositor vai para o diálogo, não para a linha do comentário.
        body.addEventListener('htmx:beforeSwap', (event) => {
          const detail = event.detail || {};
          const element = detail.elt;
          if (!element || typeof element.closest !== 'function' || !element.closest(triggerSelector)) {
            return;
          }
          const bodyEl = getDialogBody();
          if (!bodyEl) {
            return;
          }
          detail.target = bodyEl;
          detail.shouldSwap = true;
          detail.isError = false;
        });

        // Depois de trocar o corpo do diálogo: abre o modal, ou fecha se a postagem foi publicada.
        body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (!target || target.id !== dialogBodyId) {
            return;
          }
          const dialog = getDialog();
          const composer = target.querySelector('[data-discussion-reply-composer]');
          if (!composer) {
            // Sem compositor na resposta: a postagem foi publicada. Recarrega para mostrar o comentário.
            if (dialog && dialog.open) {
              dialog.close();
            }
            window.location.reload();
            return;
          }
          if (dialog && !dialog.open) {
            dialog.showModal();
          }
          focusComposer(composer);
        });

        // Limpar: esvazia o editor e os campos do formulário, sem depender do reset nativo.
        body.addEventListener('click', (event) => {
          const clear = event.target.closest && event.target.closest('[data-aculta-reply-clear]');
          if (!clear) {
            return;
          }
          event.preventDefault();
          const form = clear.closest('form');
          if (!form) {
            return;
          }
          const editor = editorFor(form);
          if (editor) {
            editor.setData('');
          }
          form.reset();
          form.querySelectorAll('textarea').forEach((textarea) => {
            textarea.value = '';
          });
        });
      });

      // Ao fechar, esvazia o corpo para o próximo Responder/Citar começar limpo.
      const dialog = getDialog();
      if (dialog && !dialog.dataset.acultaCloseBound) {
        dialog.dataset.acultaCloseBound = '1';
        dialog.addEventListener('close', () => {
          const bodyEl = getDialogBody();
          if (bodyEl) {
            bodyEl.innerHTML = '';
          }
        });
      }
    },
  };
})(Drupal, once);

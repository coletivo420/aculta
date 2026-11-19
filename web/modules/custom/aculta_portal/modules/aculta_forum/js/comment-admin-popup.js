/**
 * Controles administrativos do comentário em popup.
 *
 * O gatilho (data-action="comentario-controles") carrega o modal do comentário por HTMX no corpo
 * #aculta-admin-dialog-body, que fica na página (popup SDC). Depois da troca, abre o diálogo. Ao
 * fechar, esvazia o corpo e devolve o foco ao gatilho. Quando uma ação termina (o formulário
 * devolve o marcador data-moderation-done), a página recarrega para mostrar o novo estado.
 */
(function (Drupal, once) {
  'use strict';

  const dialogId = 'aculta-admin-dialog';
  const bodyId = 'aculta-admin-dialog-body';
  const triggerSelector = '[data-action="comentario-controles"]';
  let lastTrigger = null;

  function getDialog() {
    return document.getElementById(dialogId);
  }

  function getBody() {
    return document.getElementById(bodyId);
  }

  function closeDialog() {
    const dialog = getDialog();
    if (dialog && dialog.open) {
      dialog.close();
    }
  }

  /**
   * Atualiza só o bloco do comentário moderado, sem recarregar a página. Excluir de vez remove o
   * bloco. Se o bloco não existir na página (ou o HTMX faltar), recarrega como alternativa.
   */
  function refreshComment(cid, acao) {
    const selector = '#aculta-comment-' + cid;
    const current = document.querySelector(selector);
    closeDialog();
    if (acao === 'excluir') {
      if (current) {
        current.remove();
      }
      return;
    }
    if (!current || !window.htmx) {
      window.location.reload();
      return;
    }
    window.htmx.ajax('GET', window.location.href, { select: selector, target: selector, swap: 'outerHTML' })
      .catch(() => window.location.reload());
  }

  Drupal.behaviors.acultaCommentAdminPopup = {
    attach(context) {
      once('aculta-comment-admin-popup', document.body, context).forEach(() => {
        document.addEventListener('click', (event) => {
          const trigger = event.target && event.target.closest ? event.target.closest(triggerSelector) : null;
          const body = getBody();
          if (!trigger || !body || !window.htmx) {
            return;
          }
          event.preventDefault();
          lastTrigger = trigger;
          window.htmx.ajax('GET', trigger.getAttribute('data-hx-get'), { target: '#' + bodyId, swap: 'innerHTML' });
        });

        document.body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (!target || target.id !== bodyId) {
            return;
          }
          const dialog = getDialog();
          const done = target.querySelector('[data-moderation-done]');
          if (done) {
            refreshComment(done.dataset.cid, done.dataset.acao);
            return;
          }
          if (dialog && !dialog.open) {
            dialog.showModal();
          }
          const first = target.querySelector('button, input, select, textarea, a[href]');
          if (first) {
            first.focus();
          }
        });

        const dialog = getDialog();
        if (dialog && !dialog.dataset.acultaAdminBound) {
          dialog.dataset.acultaAdminBound = '1';
          dialog.addEventListener('close', () => {
            const body = getBody();
            if (body) {
              body.innerHTML = '';
            }
            if (lastTrigger && document.body.contains(lastTrigger)) {
              lastTrigger.focus();
            }
            lastTrigger = null;
          });
        }
      });
    },
  };
})(Drupal, once);

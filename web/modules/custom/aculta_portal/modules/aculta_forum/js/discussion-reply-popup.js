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

  /**
   * Overlay "Aguarde / Postando seu comentário" dentro do diálogo. A estrutura é criada aqui;
   * a aparência (cartão, spinner, animação) é do tema, em forum.css.
   */
  function showPostingOverlay(visible) {
    const dialog = document.getElementById(dialogId);
    if (!dialog) {
      return;
    }
    let overlay = dialog.querySelector('.aculta-posting-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'aculta-posting-overlay';
      overlay.setAttribute('role', 'status');
      overlay.setAttribute('aria-live', 'polite');
      overlay.hidden = true;
      overlay.innerHTML = '<div class="aculta-posting-overlay__card">'
        + '<span class="aculta-posting-overlay__spinner" aria-hidden="true"></span>'
        + '<p class="aculta-posting-overlay__title">Aguarde</p>'
        + '<p class="aculta-posting-overlay__text">Postando seu comentário…</p>'
        + '</div>';
      dialog.appendChild(overlay);
    }
    overlay.hidden = !visible;
  }
  // Visitante (sem sessão): Comentar, Responder e Citar abrem o popup de login já existente.
  const anonymousTriggerSelector = '[data-aculta-comment-new], [data-action="resposta-responder"], [data-action="resposta-citar"], [data-action="resposta-editar"], [data-action="resposta-apagar"]';

  function isAnonymous() {
    return !document.body.classList.contains('user-logged-in');
  }

  function openLoginPopup() {
    let trigger = document.getElementById('aculta-login-popup-trigger');
    if (!trigger) {
      trigger = document.createElement('a');
      trigger.id = 'aculta-login-popup-trigger';
      trigger.className = 'use-ajax';
      trigger.setAttribute('data-dialog-type', 'modal');
      trigger.setAttribute('data-dialog-options', JSON.stringify({ title: 'Entrar', closeText: 'Fechar' }));
      trigger.hidden = true;
      document.body.appendChild(trigger);
      Drupal.attachBehaviors(trigger);
    }
    const destination = window.location.pathname + window.location.search;
    trigger.setAttribute('href', '/entrar/janela?destination=' + encodeURIComponent(destination));
    trigger.click();
  }

  const triggerSelector = '[data-action="resposta-responder"], [data-action="resposta-citar"], [data-action="resposta-editar"], [data-action="resposta-apagar"]';

  function ensureDialog() {
    let dialog = document.getElementById(dialogId);
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = dialogId;
      dialog.className = 'aculta-dialog';
      dialog.setAttribute('aria-modal', 'true');
      dialog.innerHTML = '<div class="aculta-dialog__inner">'
        + '<div class="aculta-dialog__header">'
        + '<h2 class="aculta-dialog__title">Discussão</h2>'
        + '<button type="button" class="aculta-dialog__close" data-aculta-dialog-close aria-label="Fechar">✕</button>'
        + '</div>'
        + '<div class="aculta-dialog__content"><div id="' + dialogBodyId + '" class="aculta-reply-dialog__body"></div></div>'
        + '</div>';
      document.body.appendChild(dialog);
    }
    if (!document.getElementById(dialogBodyId)) {
      const container = dialog.querySelector('.aculta-dialog__content') || dialog.querySelector('.aculta-dialog__inner') || dialog;
      container.insertAdjacentHTML('beforeend', '<div id="' + dialogBodyId + '" class="aculta-reply-dialog__body"></div>');
    }
    return dialog;
  }

  function getDialog() {
    return ensureDialog();
  }

  function getDialogBody() {
    ensureDialog();
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
        // Visitante: os gatilhos abrem o popup de login, sem abrir o compositor.
        document.addEventListener('click', (event) => {
          if (!isAnonymous()) {
            return;
          }
          const trigger = event.target && event.target.closest ? event.target.closest(anonymousTriggerSelector) : null;
          if (!trigger) {
            return;
          }
          event.preventDefault();
          event.stopImmediatePropagation();
          openLoginPopup();
        }, true);

        // Garante que o corpo do diálogo exista antes de o HTMX processar os links na página.
        ensureDialog();

        // Responder/Citar: antes de o HTMX ler o alvo, o link passa a apontar para o corpo do diálogo.
        // Fase de captura: roda antes do listener do HTMX no próprio link.
        document.addEventListener('click', (event) => {
          const link = event.target && event.target.closest ? event.target.closest(triggerSelector) : null;
          const bodyEl = getDialogBody();
          if (!link || !bodyEl) {
            return;
          }
          link.setAttribute('data-hx-target', '#' + dialogBodyId);
          link.setAttribute('hx-target', '#' + dialogBodyId);
          link.setAttribute('data-hx-swap', 'innerHTML');
          link.setAttribute('hx-swap', 'innerHTML');
        }, true);

        // Rede de segurança: se o alvo ainda assim chegar à linha do comentário, redireciona no swap.
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
            // Sem compositor na resposta: a postagem foi publicada. Vai para a página final do
            // comentário (URL após o redirecionamento do permalink); se for a mesma, recarrega.
            if (dialog && dialog.open) {
              dialog.close();
            }
            // Sem compositor na resposta. O envio já redireciona pelo servidor (HX-Redirect para o
            // permalink do tópico); este ramo só recarrega se a resposta vier como página comum.
            window.location.reload();
            return;
          }
          if (dialog && !dialog.open) {
            dialog.showModal();
          }
          focusComposer(composer);
        });

        // Aviso "Aguarde" durante a postagem: aparece ao enviar e some se a resposta trouxer erro.
        body.addEventListener('htmx:beforeRequest', (event) => {
          const el = event.detail && event.detail.elt;
          if (el && el.closest && el.closest('#' + dialogId) && (el.matches('[type="submit"]') || el.closest('form'))) {
            showPostingOverlay(true);
          }
        });
        body.addEventListener('htmx:responseError', () => showPostingOverlay(false));
        body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === dialogBodyId && target.querySelector('[data-discussion-reply-composer]')) {
            showPostingOverlay(false);
          }
        });

        // Publicar: copia o conteúdo do CKEditor para o textarea antes do HTMX ler os campos do formulário.
        // (Em fase de captura: roda antes do clique do HTMX no botão.)
        document.addEventListener('click', (event) => {
          const submit = event.target.closest && event.target.closest('#aculta-reply-dialog [type="submit"]');
          if (!submit || !Drupal.CKEditor5Instances) {
            return;
          }
          const form = submit.closest('form');
          [...Drupal.CKEditor5Instances.values()].forEach((instance) => {
            if (form && form.contains(instance.sourceElement)) {
              instance.updateSourceElement();
            }
          });
        }, true);

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

      // Comentar (topo do tópico): o compositor de comentário de topo é o mesmo endpoint de Responder, sem "resposta".
      const newBtn = document.querySelector('[data-aculta-comment-new]');
      const replyLink = document.querySelector('[data-action="resposta-responder"][data-hx-get]');
      if (newBtn && isAnonymous()) {
        newBtn.hidden = false;
      }
      if (newBtn && replyLink && !newBtn.dataset.acultaBound) {
        newBtn.dataset.acultaBound = '1';
        const url = new URL(replyLink.getAttribute('data-hx-get'), window.location.href);
        url.searchParams.delete('resposta');
        url.searchParams.delete('modo');
        newBtn.hidden = false;
        newBtn.addEventListener('click', () => {
          if (window.htmx && getDialogBody()) {
            window.htmx.ajax('GET', url.toString(), { target: '#' + dialogBodyId, swap: 'innerHTML' });
          }
        });
      }

      // Garante o corpo do diálogo: o slot do SDC não o entrega, então o JS cria a div dentro do conteúdo.
      const dialogContent = getDialog() ? getDialog().querySelector('.aculta-dialog__content') : null;
      if (dialogContent && !getDialogBody()) {
        dialogContent.insertAdjacentHTML('beforeend', '<div id="' + dialogBodyId + '" class="aculta-reply-dialog__body"></div>');
      }

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

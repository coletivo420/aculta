/**
 * Compositor de criação de conteúdo em popup no Aculta Studio (Marley).
 *
 * A ação "Nova publicação" (data-action="conteudo-criar") carrega o formulário de revista por
 * HTMX no diálogo #aculta-studio-create-dialog. O envio é HTMX: erro de validação mantém o
 * popup; sucesso responde HX-Redirect para a publicação criada.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const dialogId = 'aculta-studio-create-dialog';
  const dialogBodyId = 'aculta-studio-create-body';
  const triggerSelector = '[data-action="conteudo-criar"]';

  function ensureDialog() {
    let dialog = document.getElementById(dialogId);
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = dialogId;
      dialog.className = 'aculta-dialog';
      dialog.setAttribute('aria-modal', 'true');
      dialog.setAttribute('aria-labelledby', dialogId + '-title');
      dialog.innerHTML = '<div class="aculta-dialog__inner">'
        + '<div class="aculta-dialog__header">'
        + '<h2 class="aculta-dialog__title" id="' + dialogId + '-title">Nova publicação</h2>'
        + '<button type="button" class="aculta-dialog__close" data-aculta-dialog-close aria-label="Fechar">✕</button>'
        + '</div>'
        + '<div class="aculta-dialog__content"><div id="' + dialogBodyId + '" class="aculta-reply-dialog__body"></div></div>'
        + '</div>';
      document.body.appendChild(dialog);
    }
    return dialog;
  }

  function getBody() {
    ensureDialog();
    return document.getElementById(dialogBodyId);
  }

  /**
   * Destrói as instâncias do CKEditor 5 dentro do elemento e as tira do registro do Drupal.
   */
  function destroyEditors(element) {
    if (!Drupal.CKEditor5Instances) {
      return;
    }
    [...Drupal.CKEditor5Instances.entries()].forEach(([id, instance]) => {
      if (element.contains(instance.sourceElement)) {
        instance.destroy().catch(() => {});
        Drupal.CKEditor5Instances.delete(id);
      }
    });
  }

  function hasEditor(element) {
    if (!Drupal.CKEditor5Instances) {
      return false;
    }
    return [...Drupal.CKEditor5Instances.values()].some((instance) => element.contains(instance.sourceElement));
  }

  Drupal.behaviors.acultaStudioCreatePopup = {
    attach(context) {
      once('aculta-studio-create-popup', document.body, context).forEach(() => {
        ensureDialog();

        // O clique nunca segue o href (que aponta para o fragmento do popup). Se o HTMX não
        // processou o link, o popup é carregado aqui mesmo.
        document.addEventListener('click', (event) => {
          const trigger = event.target && event.target.closest ? event.target.closest(triggerSelector) : null;
          if (!trigger) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          const url = trigger.getAttribute('data-hx-get') || trigger.getAttribute('href');
          ensureDialog();
          if (window.htmx) {
            window.htmx.ajax('GET', url, { target: '#' + dialogBodyId, swap: 'innerHTML', select: '[data-aculta-content-composer]' });
          }
        }, true);

        // Mensagens de erro do envio: o recorte HTMX traz só o formulário, então guarda as
        // mensagens da resposta e as recoloca no topo do popup depois da troca.
        let pendingMessages = '';
        document.body.addEventListener('htmx:beforeSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === 'aculta-studio-create-body' && event.detail.serverResponse) {
            const doc = new DOMParser().parseFromString(event.detail.serverResponse, 'text/html');
            const msgs = doc.querySelector('[data-drupal-messages]');
            pendingMessages = msgs ? msgs.innerHTML : '';
          }
        });
        document.body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === 'aculta-studio-create-body' && pendingMessages) {
            target.insertAdjacentHTML('afterbegin', '<div data-drupal-messages>' + pendingMessages + '</div>');
            pendingMessages = '';
          }
        });

        // Publicar: copia o CKEditor para o textarea antes do HTMX ler os campos.
        document.addEventListener('click', (event) => {
          const submit = event.target.closest && event.target.closest('#' + dialogId + ' [type="submit"]');
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

        // Antes de trocar o corpo: destrói os editores do formulário anterior.
        document.body.addEventListener('htmx:beforeSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === dialogBodyId) {
            destroyEditors(target);
          }
        });

        // Depois da troca: inicializa o formulário e abre o diálogo. Erro de validação reabre o
        // mesmo diálogo, já aberto.
        document.body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (!target || target.id !== dialogBodyId) {
            return;
          }
          Drupal.attachBehaviors(target, drupalSettings);
          [250, 700].forEach((delay) => setTimeout(() => {
            if (target.querySelector('textarea') && !hasEditor(target)) {
              Drupal.attachBehaviors(target, drupalSettings);
            }
          }, delay));
          const dialog = ensureDialog();
          if (!dialog.open) {
            dialog.showModal();
          }
        });

        // Ao fechar, destrói os editores e esvazia o corpo para a próxima abertura.
        const dialog = ensureDialog();
        if (!dialog.dataset.acultaCloseBound) {
          dialog.dataset.acultaCloseBound = '1';
          dialog.addEventListener('close', () => {
            const body = getBody();
            if (body) {
              destroyEditors(body);
              body.innerHTML = '';
            }
          });
        }
      });
    },
  };
})(Drupal, once, drupalSettings);

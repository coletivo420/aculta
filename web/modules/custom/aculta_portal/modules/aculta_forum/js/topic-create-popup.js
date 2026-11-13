/**
 * Criar tópico em popup (mesmo padrão do popup Responder/Citar da discussão).
 *
 * O botão "Criar tópico" (data-action="topico-criar") carrega o formulário do tópico por HTMX
 * dentro do diálogo #aculta-topic-dialog. O envio é HTMX: se o formulário voltar, houve erro
 * de validação e o popup continua aberto; se o servidor responder HX-Redirect, o navegador vai
 * para o tópico criado.
 *
 * Visitante (sem sessão): o botão abre o popup de login existente, como nas respostas.
 */
(function (Drupal, once) {
  'use strict';

  const dialogId = 'aculta-topic-dialog';
  const dialogBodyId = 'aculta-topic-dialog-body';
  const triggerSelector = '[data-action="topico-criar"]';

  function isAnonymous() {
    if (window.drupalSettings && window.drupalSettings.user && typeof window.drupalSettings.user.uid !== 'undefined') {
      return parseInt(window.drupalSettings.user.uid, 10) === 0;
    }
    return !document.body.classList.contains('user-logged-in');
  }

  function openLoginPopup() {
    const destination = window.location.pathname + window.location.search;
    const loginLink = document.querySelector('.aculta-account-menu a[href*="/entrar/janela"]');
    if (loginLink) {
      loginLink.click();
      return;
    }
    window.location.href = '/entrar/janela?destination=' + encodeURIComponent(destination);
  }

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
        + '<h2 class="aculta-dialog__title" id="' + dialogId + '-title">Criar tópico</h2>'
        + '<button type="button" class="aculta-dialog__close" data-aculta-dialog-close aria-label="Fechar">✕</button>'
        + '</div>'
        + '<div class="aculta-dialog__content"><div id="' + dialogBodyId + '" class="aculta-reply-dialog__body"></div></div>'
        + '</div>';
      document.body.appendChild(dialog);
    }
    return dialog;
  }

  /**
   * Destrói as instâncias do CKEditor 5 que estão dentro do elemento e as tira do registro do Drupal.
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

  function getBody() {
    ensureDialog();
    return document.getElementById(dialogBodyId);
  }

  Drupal.behaviors.acultaForumTopicCreatePopup = {
    attach(context) {
      once('aculta-topic-create-popup', document.body, context).forEach(() => {
        // Visitante: o botão abre o popup de login, sem carregar o formulário.
        document.addEventListener('click', (event) => {
          const trigger = event.target && event.target.closest ? event.target.closest(triggerSelector) : null;
          if (!trigger || !isAnonymous()) {
            return;
          }
          event.preventDefault();
          event.stopImmediatePropagation();
          openLoginPopup();
        }, true);

        // Garante o corpo do diálogo antes de o HTMX processar o link.
        ensureDialog();
        // Mensagens de erro do envio: o recorte HTMX traz só o formulário, então guarda as
        // mensagens da resposta e as recoloca no topo do popup depois da troca.
        let pendingMessages = null;
        document.body.addEventListener('htmx:beforeSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === 'aculta-topic-dialog-body' && event.detail.serverResponse) {
            const doc = new DOMParser().parseFromString(event.detail.serverResponse, 'text/html');
            const msgs = doc.querySelector('[data-drupal-messages]');
            pendingMessages = msgs ? document.importNode(msgs, true) : null;
          }
        });
        document.body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === 'aculta-topic-dialog-body' && pendingMessages) {
            target.prepend(pendingMessages);
            pendingMessages = null;
          }
        });

        // Publicar: copia o conteúdo do CKEditor para o textarea antes do HTMX ler os campos.
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

        // Antes de trocar o corpo: destrói os editores do formulário anterior, para não sobrar
        // instância órfã nem duplicar o editor.
        document.body.addEventListener('htmx:beforeSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (target && target.id === dialogBodyId) {
            destroyEditors(target);
          }
        });

        // Depois de carregar o formulário no popup: abre o diálogo. Erro de validação reabre o
        // mesmo diálogo (já aberto), sem fechar.
        document.body.addEventListener('htmx:afterSwap', (event) => {
          const target = event.detail && event.detail.target;
          if (!target || target.id !== dialogBodyId) {
            return;
          }
          // Inicializa o CKEditor e os demais comportamentos no formulário novo do popup.
          Drupal.attachBehaviors(target, drupalSettings);
          // O script do CKEditor pode chegar depois da troca. Se o editor ainda não existir,
          // anexa de novo; o once() do Drupal evita duplicar a instância.
          [250, 700].forEach((delay) => setTimeout(() => {
            if (target.querySelector('textarea') && !hasEditor(target)) {
              Drupal.attachBehaviors(target, drupalSettings);
            }
          }, delay));
          const dialog = ensureDialog();
          if (!dialog.open) {
            dialog.showModal();
          }
          const field = target.querySelector('input[type="text"], textarea');
          if (field) {
            field.focus();
          }
        });

        // Ao fechar, esvazia o corpo para a próxima abertura começar limpa.
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
})(Drupal, once);

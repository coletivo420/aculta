(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaMessengerDrawers = {
    attach(context) {
      once('aculta-messenger-drawer-conversation-view', '[data-aculta-conversation-view]', context).forEach((conversation) => {
        const history = conversation.querySelector('.field--name-private-messages');
        if (history) {
          window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
              history.scrollTop = history.scrollHeight;
            });
          });
        }
      });

      // "Iniciar conversa" on a profile: open the bar's drawer already on the new-conversation step with that member.
      once('aculta-open-conversation', '[data-aculta-open-conversation]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          const trigger = document.querySelector('.aculta-messenger-trigger');
          const drawer = document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"]');
          if (!trigger || !drawer || typeof drawer.acultaNavigate !== 'function') {
            return;
          }
          event.preventDefault();
          // The trigger opens the drawer (its own offcanvas); the view then loads the new-conversation step.
          trigger.click();
          drawer.acultaNavigate(link.dataset.acultaOpenConversation || link.href);
        });
      });

      // "Bloquear" and "Desfazer amizade" in a member's box open their confirmation as a Drupal modal. The question is a
      // hidden container in the same form, so "Sim" still submits. The modal stays in the drawer and is centred on the
      // drawer: the scrolling content clips whatever is drawn outside its box, and that part would not receive clicks.
      // "Cancelar" closes it. The open button names its dialog.
      once('aculta-dialog-open', '[data-aculta-dialog-open]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          // Form API renders these as submit inputs: the click only opens the dialog, it does not post the form.
          event.preventDefault();
          // Each member's box has its own dialog with the same name: the open button keeps a reference to its own.
          // The drawer's scroller is resolved once, before jQuery UI moves the question: afterwards closest() finds the
          // dialog's own content, which carries the same class.
          if (!button.acultaDialogContent) {
            const form = button.closest('form');
            button.acultaDialogContent = form?.querySelector(`[data-aculta-dialog="${button.dataset.acultaDialogOpen}"]`) || null;
            button.acultaScroller = button.acultaDialogContent?.closest('.ui-dialog-content') || null;
          }
          const content = button.acultaDialogContent;
          const scroller = button.acultaScroller;
          if (!content || typeof Drupal.dialog !== 'function') {
            return;
          }
          // Bootstrap's [hidden] rule uses !important, so jQuery UI cannot show the question while the attribute stays.
          content.removeAttribute('hidden');
          // The drawer's scrolling content clips what is drawn outside its box, and the centred modal is partly outside it.
          // While the question is open that content is not clipped; it goes back to normal when the question closes.
          scroller?.setAttribute('data-aculta-confirming', '');
          if (!content.acultaDialog) {
            content.acultaDialog = Drupal.dialog(content, {
              appendTo: button.closest('form'),
              // Form API renders the open button as an <input type="button">: its label is the value, not the text.
              title: button.value || button.textContent.trim(),
              closeOnEscape: true,
              // The default close detaches the element's behaviors ('unload'), and jQuery UI then destroys the dialog
              // with its content: the question is gone at the next opening. Closing only restores the scroller here.
              close: () => scroller?.removeAttribute('data-aculta-confirming'),
            });
          }
          content.acultaDialog.showModal();
        });
      });

      once('aculta-dialog-close', '[data-aculta-dialog-close]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          button.closest('[data-aculta-dialog]')?.acultaDialog?.close();
        });
      });

      once('aculta-messenger-drawer', '[data-aculta-drawer-url]', context).forEach((drawer) => {
        let request;
        const content = drawer.querySelector('[data-aculta-drawer-content]');

        // init carries a POST body for the new-conversation form; the answer replaces the drawer content either way.
        const navigate = (url, init = {}) => {
          if (!content || !url) {
            return;
          }
          request?.abort();
          const activeRequest = new AbortController();
          request = activeRequest;
          content.setAttribute('aria-busy', 'true');
          fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal,
            ...init,
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Drawer navigation failed.');
              }
              updateCounter('messages', response.headers.get('X-Aculta-Messages-Count'));
              return response.text();
            })
            .then((html) => {
              // A request cancelled by navigation or drawer closing must never
              // overwrite the current panel after response.text() resolves.
              if (activeRequest.signal.aborted || request !== activeRequest) {
                return;
              }
              const template = document.createElement('template');
              template.innerHTML = html;
              Drupal.detachBehaviors(content, null, 'unload');
              content.replaceChildren(template.content.cloneNode(true));
              Drupal.attachBehaviors(content);
            })
            .catch((error) => {
              if (!activeRequest.signal.aborted && request === activeRequest && error.name !== 'AbortError') {
                content.replaceChildren(document.createTextNode(Drupal.t('Não foi possível carregar esta conversa.')));
              }
            })
            .finally(() => {
              if (request === activeRequest) {
                content.setAttribute('aria-busy', 'false');
                request = null;
              }
            });
        };

        // Lets other parts of the page (for example the profile's "Iniciar conversa") load a drawer view.
        drawer.acultaNavigate = navigate;

        // Gmail-style entry: a comma or Enter turns the typed name into a chip, and pasted "a, b, c" makes one chip per name.
        drawer.addEventListener('keydown', (event) => {
          const input = event.target;
          if (!(input instanceof HTMLInputElement) || input.name !== 'q' || !input.form?.matches('form[data-aculta-new-conversation]')) {
            return;
          }
          if (event.key !== ',' && event.key !== 'Enter') {
            return;
          }
          event.preventDefault();
          const name = input.value.replace(/,/g, '').trim();
          if (name !== '') {
            commitTypedMember(input, name);
          }
        });
        drawer.addEventListener('input', (event) => {
          const input = event.target;
          if (!(input instanceof HTMLInputElement) || input.name !== 'q' || !input.value.includes(',')) {
            return;
          }
          // Pasted or typed "a, b, c": every name before the last comma becomes a chip; the last one stays in the field.
          const parts = input.value.split(',');
          const last = parts.pop();
          input.value = last;
          parts.map((part) => part.trim()).filter((part) => part !== '').forEach((part) => commitTypedMember(input, part, false));
        });

        drawer.addEventListener('click', (event) => {
          // A search result becomes a chip in the field above the results; the chip's "×" takes it off again.
          const add = event.target.closest('[data-aculta-add-member]');
          if (add && drawer.contains(add)) {
            event.preventDefault();
            addMemberChip(add.closest('form'), add.dataset.acultaAddMember, add.dataset.acultaMemberName);
            return;
          }
          const remove = event.target.closest('[data-aculta-chip-remove]');
          if (remove && drawer.contains(remove)) {
            event.preventDefault();
            remove.closest('[data-aculta-chip]')?.remove();
            return;
          }
          // The "Iniciar nova conversa" button carries data-aculta-conversation-url too; it is not in a form.
          const link = event.target.closest('a[data-aculta-conversation-url], button[data-aculta-conversation-url], input[data-aculta-conversation-url], a[data-aculta-drawer-back]');
          if (!link || !drawer.contains(link)) {
            return;
          }
          event.preventDefault();
          navigate(link.dataset.acultaConversationUrl || link.href);
        });

        drawer.addEventListener('submit', (event) => {
          // The new-conversation form is one form (search, chips, message): its "Enviar" posts everything below.
          // Enter in the member search never sends (the keydown above stops it).
          // First message of a new conversation: posted with fetch, because the Form API AJAX needs page settings.
          // The "Opções" actions (friendship, block) post the same way and answer with the updated conversation.
          if (event.target.matches('form[data-aculta-new-conversation-form], form[data-aculta-drawer-action], form[data-aculta-reply-form]')) {
            event.preventDefault();
            const body = new FormData(event.target);
            if (event.submitter?.name) {
              body.append(event.submitter.name, event.submitter.value);
            }
            // A reply is posted without Form API keys (form_id would make the native form process it); its token
            // travels as reply_token and the reply route checks it against the reply form.
            if (event.target.matches('form[data-aculta-reply-form]')) {
              const token = body.get('form_token') || '';
              ['form_build_id', 'form_id', 'form_token', 'op'].forEach((key) => body.delete(key));
              body.set('reply_token', token);
            }
            navigate(event.target.action, { method: 'POST', body });
          }
        });

        drawer.addEventListener('show.bs.offcanvas', () => {
          const endpoint = drawer.dataset.acultaDrawerUrl;
          if (!content || !endpoint) {
            return;
          }

          request?.abort();
          const activeRequest = new AbortController();
          request = activeRequest;
          content.setAttribute('aria-busy', 'true');

          fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal,
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Drawer request failed.');
              }
              const counterHeader = drawer.dataset.acultaDrawerCount === 'messages'
                ? 'X-Aculta-Messages-Count'
                : 'X-Aculta-Alerts-Count';
              updateCounter(drawer.dataset.acultaDrawerCount, response.headers.get(counterHeader));
              return response.text();
            })
            .then((html) => {
              // A request cancelled by navigation or drawer closing must never
              // overwrite the current panel after response.text() resolves.
              if (activeRequest.signal.aborted || request !== activeRequest) {
                return;
              }
              const template = document.createElement('template');
              template.innerHTML = html;
              // Detach behaviors of the replaced content, as navigate() does, so listeners do not accumulate.
              Drupal.detachBehaviors(content, null, 'unload');
              content.replaceChildren(template.content.cloneNode(true));
              Drupal.attachBehaviors(content);
            })
            .catch((error) => {
              if (!activeRequest.signal.aborted && request === activeRequest && error.name !== 'AbortError') {
                content.replaceChildren(document.createTextNode(drawer.dataset.acultaDrawerLabel || Drupal.t('Não foi possível carregar.')));
              }
            })
            .finally(() => {
              if (request === activeRequest) {
                content.setAttribute('aria-busy', 'false');
                request = null;
              }
            });
        });

        drawer.addEventListener('hide.bs.offcanvas', () => request?.abort());
      });
    },
  };

  // "Opções" of a conversation row: the row carries the address of its menu, and the menu is loaded the first time the
  // member opens it. 'toggle' does not bubble, so the listener is on the document, in the capture phase.
  document.addEventListener('toggle', (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open) {
      return;
    }
    // The Bootstrap accordion template puts the element's attributes on its wrapper, around the details.
    const box = details.closest('[data-aculta-options-url]');
    const panel = details.querySelector('.aculta-messenger-options__panel');
    if (!box || !panel || panel.childElementCount > 0 || box.dataset.acultaOptionsLoading === 'true') {
      return;
    }
    box.dataset.acultaOptionsLoading = 'true';
    fetch(box.dataset.acultaOptionsUrl, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Options request failed.');
        }
        return response.text();
      })
      .then((html) => {
        const template = document.createElement('template');
        template.innerHTML = html;
        panel.replaceChildren(template.content.cloneNode(true));
        Drupal.attachBehaviors(panel);
      })
      .catch(() => {
        panel.replaceChildren(document.createTextNode(Drupal.t('Não foi possível carregar as opções.')));
      })
      .finally(() => {
        delete box.dataset.acultaOptionsLoading;
      });
  }, true);

  // Looks the typed name up in the member search and adds that member as a chip. The field is cleared when it worked;
  // when no member has that name, the typed text stays and the form says so.
  function commitTypedMember(input, name, clearField = true) {
    const form = input.form;
    const previewUrl = input.dataset.previewUrl;
    if (!form || !previewUrl) {
      return;
    }
    fetch(`${previewUrl}?q=${encodeURIComponent(name)}`, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((response) => (response.ok ? response.text() : ''))
      .then((html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const wanted = name.toLocaleLowerCase('pt-BR');
        const match = [...doc.querySelectorAll('[data-aculta-add-member]')]
          .find((option) => (option.dataset.acultaMemberName || '').toLocaleLowerCase('pt-BR') === wanted);
        if (!match) {
          showChipStatus(form, Drupal.t('Nenhum membro com o nome "@name".', { '@name': name }));
          return;
        }
        addMemberChip(form, match.dataset.acultaAddMember, match.dataset.acultaMemberName);
        if (clearField) {
          input.value = '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      })
      .catch(() => {});
  }

  function showChipStatus(form, text) {
    const box = form?.querySelector('[data-aculta-chips]');
    if (!box) {
      return;
    }
    const note = document.createElement('span');
    note.className = 'aculta-chips__status';
    note.setAttribute('role', 'status');
    note.textContent = text;
    box.append(note);
    window.setTimeout(() => note.remove(), 2500);
  }

  // Adds a member as a chip (name and "×") to the search form's field, up to the group limit of the form.
  function addMemberChip(form, uid, name) {
    const box = form?.querySelector('[data-aculta-chips]');
    if (!box || !uid || box.querySelector(`input[name="members[]"][value="${uid}"]`)) {
      return;
    }
    const max = Number.parseInt(form.dataset.acultaGroupMax || '10', 10);
    if (box.querySelectorAll('input[name="members[]"]').length >= max) {
      const note = document.createElement('span');
      note.className = 'aculta-chips__status';
      note.setAttribute('role', 'status');
      note.textContent = Drupal.t('Limite de @n pessoas.', { '@n': max });
      box.append(note);
      window.setTimeout(() => note.remove(), 2500);
      return;
    }
    const chip = document.createElement('span');
    chip.className = 'aculta-chip';
    chip.dataset.acultaChip = '';
    const label = document.createElement('span');
    label.className = 'aculta-chip__name';
    label.textContent = name;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'aculta-chip__remove';
    remove.dataset.acultaChipRemove = '';
    remove.setAttribute('aria-label', Drupal.t('Remover @name', { '@name': name }));
    remove.textContent = '×';
    const field = document.createElement('input');
    field.type = 'hidden';
    field.name = 'members[]';
    field.value = uid;
    chip.append(label, remove, field);
    box.append(chip);
    clearSearchField(form);
  }

  // After a member is chosen the search is emptied (its results go with it) and the field takes focus, so the next name
  // can be typed right away. The field and the Continuar button stay on screen the whole time.
  function clearSearchField(form) {
    const search = form?.querySelector('input[name="q"]');
    if (!search) {
      return;
    }
    search.value = '';
    search.dispatchEvent(new Event('input', { bubbles: true }));
    search.focus();
  }

  function updateCounter(type, value) {
    const count = Number.parseInt(value || '', 10);
    if (!Number.isFinite(count) || count < 0) {
      return;
    }

    const selector = type === 'messages' ? '[data-aculta-messenger-count]' : '[data-aculta-alerts-count]';
    const counter = document.querySelector(selector);
    if (!counter) {
      return;
    }

    // Badges show 99+ above 99; the exact number stays in data-count for scripts and assistive labels.
    counter.textContent = count > 99 ? '99+' : String(count);
    counter.dataset.count = String(count);
    const trigger = counter.closest('button');
    if (trigger) {
      const label = type === 'messages'
        ? (count === 1 ? Drupal.t('1 conversa não lida. Abrir mensagens') : Drupal.t('@count conversas não lidas. Abrir mensagens', { '@count': count }))
        : (count === 1 ? Drupal.t('1 aviso ativo ou não lido. Abrir avisos') : Drupal.t('@count avisos ativos ou não lidos. Abrir avisos', { '@count': count }));
      trigger.setAttribute('aria-label', label);
    }
  }
})(Drupal, once, drupalSettings);

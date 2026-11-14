(function (Drupal, once) {
  'use strict';

  let confirmationIndex = 0;

  function drawerFor(element) {
    return element instanceof Element ? element.closest('[data-aculta-utility-drawer]') : null;
  }

  function contentFor(drawer) {
    const content = drawer?.querySelector('[data-aculta-drawer-content]');
    if (content && !content.id) {
      content.id = `aculta-drawer-content-${drawer.dataset.acultaDrawerCount || 'messages'}`;
    }
    return content;
  }

  function configureDrawerHtmx(element) {
    if (!(element instanceof Element)) {
      return;
    }
    const drawer = drawerFor(element);
    const content = contentFor(drawer);
    if (!drawer || !content) {
      return;
    }

    if (element.matches('a[data-aculta-conversation-url], button[data-aculta-conversation-url], input[data-aculta-conversation-url], a[data-aculta-drawer-back], a[data-aculta-drawer-filter]')) {
      const url = element.dataset.acultaDrawerFilterUrl || element.dataset.acultaConversationUrl || element.href;
      if (url && !element.hasAttribute('data-hx-get')) {
        element.setAttribute('data-hx-get', url);
        element.setAttribute('data-hx-target', `#${CSS.escape(content.id)}`);
        element.setAttribute('data-hx-swap', 'innerHTML');
      }
    }

    if (element.matches('form[data-aculta-new-conversation-form], form[data-aculta-drawer-action], form[data-aculta-reply-form]')) {
      element.setAttribute('data-hx-post', element.action);
      element.setAttribute('data-hx-target', `#${CSS.escape(content.id)}`);
      element.setAttribute('data-hx-swap', 'innerHTML');
    }

    if (element.matches('[data-aculta-options-url]')) {
      const panel = element.querySelector('.aculta-messenger-options__panel');
      if (panel) {
        if (!panel.id) {
          panel.id = `aculta-messenger-options-${++confirmationIndex}`;
        }
        element.setAttribute('data-hx-get', element.dataset.acultaOptionsUrl);
        element.setAttribute('data-hx-trigger', 'toggle once');
        element.setAttribute('data-hx-target', `#${CSS.escape(panel.id)}`);
        element.setAttribute('data-hx-swap', 'innerHTML');
      }
    }
  }

  function showDrawerError(target, drawer) {
    if (!(target instanceof Element) || !drawer?.contains(target)) {
      return;
    }
    const message = drawer.dataset.acultaDrawerLabel
      || (drawer.dataset.acultaDrawerCount === 'alerts'
        ? Drupal.t('Não foi possível carregar estes avisos.')
        : Drupal.t('Não foi possível carregar esta conversa.'));
    const feedback = document.createElement('div');
    feedback.className = 'aculta-messenger-state aculta-messenger-state--error';
    feedback.setAttribute('role', 'alert');
    feedback.dataset.acultaDrawerState = 'error';
    feedback.textContent = message;
    target.replaceChildren(feedback);
    target.setAttribute('aria-busy', 'false');
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

  function clearSearchField(form) {
    const search = form?.querySelector('input[name="q"]');
    if (!search) {
      return;
    }
    search.value = '';
    search.dispatchEvent(new Event('input', { bubbles: true }));
    search.focus();
  }

  function addMemberChip(form, uid, name) {
    const box = form?.querySelector('[data-aculta-chips]');
    if (!box || !uid || box.querySelector(`input[name="members[]"][value="${CSS.escape(uid)}"]`)) {
      return false;
    }
    const max = Number.parseInt(form.dataset.acultaGroupMax || '10', 10);
    if (box.querySelectorAll('input[name="members[]"]').length >= max) {
      showChipStatus(form, Drupal.t('Limite de @n pessoas.', { '@n': max }));
      return false;
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
    return true;
  }

  function processMemberLookup(button, input, item) {
    const form = input.form;
    const target = document.getElementById(input.dataset.previewTarget || '');
    if (!form || !target) {
      return;
    }
    const wanted = item.name.toLocaleLowerCase('pt-BR');
    const match = [...target.querySelectorAll('[data-aculta-add-member]')]
      .find((option) => (option.dataset.acultaMemberName || '').toLocaleLowerCase('pt-BR') === wanted);
    if (match) {
      const didAdd = addMemberChip(form, match.dataset.acultaAddMember, match.dataset.acultaMemberName);
      if (!didAdd && item.clearField) {
        input.value = '';
      }
    }
    else {
      showChipStatus(form, Drupal.t('Nenhum membro com o nome "@name".', { '@name': item.name }));
    }

    const queue = button.acultaMemberQueue || [];
    const next = queue.shift();
    button.acultaMemberQueue = queue;
    if (next) {
      const url = new URL(input.dataset.previewUrl, window.location.href);
      url.searchParams.set('q', next.name);
      button.setAttribute('data-hx-get', url.href);
      button.acultaMemberCurrent = next;
      button.click();
    }
    else {
      button.acultaMemberCurrent = null;
    }
  }

  function commitTypedMember(input, name, clearField = true) {
    const form = input.form;
    const previewUrl = input.dataset.previewUrl;
    const preview = document.getElementById(input.dataset.previewTarget || '');
    if (!form || !previewUrl || !preview) {
      return;
    }
    let button = form.querySelector('[data-aculta-member-lookup]');
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.hidden = true;
      button.dataset.acultaMemberLookup = '';
      button.setAttribute('data-hx-get', previewUrl);
      button.setAttribute('data-hx-target', `#${CSS.escape(preview.id)}`);
      button.setAttribute('data-hx-swap', 'innerHTML');
      form.append(button);
      htmx.process(button);
    }
    button.acultaMemberQueue = button.acultaMemberQueue || [];
    button.acultaMemberQueue.push({ name, clearField });
    if (!button.acultaMemberCurrent) {
      const current = button.acultaMemberQueue.shift();
      const url = new URL(previewUrl, window.location.href);
      url.searchParams.set('q', current.name);
      button.setAttribute('data-hx-get', url.href);
      button.acultaMemberCurrent = current;
      button.click();
    }
  }

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

      once('aculta-open-conversation', '[data-aculta-open-conversation]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          const trigger = document.querySelector('.aculta-messenger-trigger');
          const drawer = document.querySelector('[data-aculta-drawer-url][data-aculta-drawer-count="messages"]');
          if (!trigger || !drawer) {
            return;
          }
          event.preventDefault();
          drawer.setAttribute('data-hx-get', link.dataset.acultaOpenConversation || link.href);
          if (drawer.open) {
            htmx.trigger(drawer, 'aculta:drawer:open');
          }
          else {
            trigger.click();
          }
        });
      });

      once('aculta-dialog-open', '[data-aculta-dialog-open]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          if (!button.acultaDialogContent) {
            const form = button.closest('form');
            button.acultaDialogContent = form?.querySelector(`[data-aculta-dialog="${button.dataset.acultaDialogOpen}"]`) || null;
            const content = button.acultaDialogContent;
            if (content) {
              const dialog = document.createElement('dialog');
              const titleId = `aculta-confirm-title-${++confirmationIndex}`;
              const title = content.querySelector('p');
              dialog.className = 'aculta-confirm-dialog';
              dialog.dataset.acultaConfirmDialog = '';
              if (title) {
                title.id = titleId;
                dialog.setAttribute('aria-labelledby', titleId);
              }
              else {
                dialog.setAttribute('aria-label', button.value || button.textContent.trim());
              }
              content.parentNode.insertBefore(dialog, content);
              dialog.append(content);
              content.acultaDialog = dialog;
              dialog.addEventListener('close', () => content.setAttribute('hidden', 'hidden'));
            }
          }
          const content = button.acultaDialogContent;
          const dialog = content?.acultaDialog;
          if (content && dialog instanceof HTMLDialogElement) {
            content.removeAttribute('hidden');
            dialog.showModal();
          }
        });
      });

      once('aculta-dialog-close', '[data-aculta-dialog-close]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          button.closest('[data-aculta-dialog]')?.acultaDialog?.close();
        });
      });

      once('aculta-messenger-drawer', '[data-aculta-drawer-url]', context).forEach((drawer) => {
        const content = contentFor(drawer);
        drawer.addEventListener('htmx:configRequest', (event) => {
          event.detail.headers['X-Requested-With'] = 'XMLHttpRequest';
          drawer.acultaHtmxSource = event.detail.elt;
          if (event.detail.elt.matches('form[data-aculta-reply-form]')) {
            const parameters = event.detail.parameters;
            const replyToken = parameters.get('form_token') || '';
            ['form_build_id', 'form_id', 'form_token', 'op'].forEach((name) => parameters.delete(name));
            parameters.set('reply_token', replyToken);
          }
        });
        drawer.addEventListener('htmx:beforeRequest', (event) => {
          const target = event.detail.target;
          if (target instanceof Element && drawer.contains(target)) {
            target.setAttribute('aria-busy', 'true');
            if (!target.querySelector('[data-aculta-drawer-state="loading"]')) {
              const loading = document.createElement('p');
              loading.className = 'aculta-messenger-state aculta-messenger-state--loading';
              loading.setAttribute('role', 'status');
              loading.dataset.acultaDrawerState = 'loading';
              loading.textContent = target === content
                ? Drupal.t('Carregando…')
                : Drupal.t('Carregando opções…');
              target.append(loading);
            }
          }
        });
        drawer.addEventListener('htmx:afterSettle', (event) => {
          if (event.detail.target instanceof Element && drawer.contains(event.detail.target)) {
            event.detail.target.setAttribute('aria-busy', 'false');
            event.detail.target.querySelector('[data-aculta-drawer-state="loading"]')?.remove();
          }
          if (event.detail.target === content) {
            content.querySelectorAll('[data-aculta-nojs-search]').forEach((form) => {
              form.hidden = true;
            });
            content.querySelectorAll('[data-aculta-js-only]').forEach((element) => {
              element.hidden = false;
            });
          }
          const preview = event.detail.target;
          const lookup = preview instanceof Element
            ? preview.closest('form')?.querySelector('[data-aculta-member-lookup]')
            : null;
          if (lookup?.acultaMemberCurrent) {
            processMemberLookup(lookup, lookup.closest('form').querySelector('input[name="q"]'), lookup.acultaMemberCurrent);
          }
        });
        drawer.addEventListener('htmx:responseError', (event) => showDrawerError(event.detail.target, drawer));
        drawer.addEventListener('htmx:sendError', (event) => showDrawerError(event.detail.target, drawer));
        drawer.addEventListener('close', () => {
          if (drawer.acultaHtmxSource) {
            htmx.trigger(drawer.acultaHtmxSource, 'htmx:abort');
            drawer.acultaHtmxSource = null;
          }
        });

        drawer.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && event.target instanceof Element && event.target.closest('[data-aculta-confirm-dialog]')) {
            event.stopPropagation();
            return;
          }
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
          const parts = input.value.split(',');
          input.value = parts.pop();
          parts.map((part) => part.trim()).filter((part) => part !== '').forEach((part) => commitTypedMember(input, part, false));
        });
        drawer.addEventListener('click', (event) => {
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
          }
        });
      });

      once('acultaMessengerHtmxDelegation', 'html', context).forEach(() => {
        document.body.addEventListener('htmx:beforeProcessNode', (event) => configureDrawerHtmx(event.detail?.elt));
        document.addEventListener('htmx:afterSettle', (event) => {
          const target = event.detail?.target;
          if (target instanceof Element) {
            target.querySelectorAll('[data-aculta-conversation-url], [data-aculta-drawer-back], [data-aculta-drawer-filter], form[data-aculta-new-conversation-form], form[data-aculta-drawer-action], form[data-aculta-reply-form], [data-aculta-options-url]').forEach((element) => {
              configureDrawerHtmx(element);
              htmx.process(element);
            });
          }
        });
      });
    },
  };
})(Drupal, once);

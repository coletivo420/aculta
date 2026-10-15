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

      once('aculta-messenger-conversation-menu', '.aculta-messenger-conversation', context).forEach((row) => {
        const trigger = row.querySelector('.aculta-messenger-conversation__menu-trigger');
        const menu = row.querySelector('.aculta-messenger-conversation__menu');
        if (!trigger || !menu) {
          return;
        }

        const close = (restoreFocus = false) => {
          menu.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
          row.classList.remove('aculta-messenger-conversation--menu-open');
          if (restoreFocus) {
            trigger.focus();
          }
        };
        const open = (x, y, focus = true) => {
          document.querySelectorAll('.aculta-messenger-conversation__menu:not([hidden])').forEach((otherMenu) => {
            if (otherMenu !== menu) {
              otherMenu.hidden = true;
              otherMenu.closest('.aculta-messenger-conversation')?.classList.remove('aculta-messenger-conversation--menu-open');
              otherMenu.closest('.aculta-messenger-conversation')?.querySelector('.aculta-messenger-conversation__menu-trigger')?.setAttribute('aria-expanded', 'false');
            }
          });
          menu.hidden = false;
          row.classList.add('aculta-messenger-conversation--menu-open');
          trigger.setAttribute('aria-expanded', 'true');
          menu.style.position = 'fixed';
          menu.style.left = `${Math.max(8, Math.min(x, window.innerWidth - menu.offsetWidth - 8))}px`;
          menu.style.top = `${Math.max(8, Math.min(y, window.innerHeight - menu.offsetHeight - 8))}px`;
          if (focus) {
            menu.querySelector('[role="menuitem"]')?.focus();
          }
        };

        row.addEventListener('contextmenu', (event) => {
          if (event.target.closest('.aculta-messenger-conversation__menu')) {
            return;
          }
          event.preventDefault();
          open(event.clientX, event.clientY);
        });
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (menu.hidden) {
            const bounds = trigger.getBoundingClientRect();
            open(bounds.right, bounds.bottom);
          }
          else {
            close();
          }
        });
        row.addEventListener('keydown', (event) => {
          if ((event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) && menu.hidden) {
            event.preventDefault();
            const bounds = trigger.getBoundingClientRect();
            open(bounds.right, bounds.bottom);
          }
          else if (event.key === 'Escape' && !menu.hidden) {
            event.preventDefault();
            close(true);
          }
        });
        menu.addEventListener('click', (event) => {
          if (event.target.closest('[role="menuitem"]')) {
            close();
          }
        });
        document.addEventListener('click', (event) => {
          if (!row.contains(event.target) && !menu.hidden) {
            close();
          }
        });
      });

      once('aculta-alert-dismiss', '[data-aculta-alert-dismiss]', context).forEach((link) => {
        link.addEventListener('click', () => {
          const counter = document.querySelector('[data-aculta-alerts-count]');
          const current = Number.parseInt(counter?.dataset.count || '0', 10);
          if (counter && Number.isFinite(current) && current > 0) {
            updateCounter('alerts', String(current - 1));
          }
          link.closest('li')?.remove();
        });
      });

      once('aculta-messenger-drawer', '[data-aculta-drawer-url]', context).forEach((drawer) => {
        let request;
        const content = drawer.querySelector('[data-aculta-drawer-content]');

        const navigate = (url) => {
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
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Drawer navigation failed.');
              }
              updateCounter('messages', response.headers.get('X-Aculta-Messages-Count'));
              return response.text();
            })
            .then((html) => {
              const template = document.createElement('template');
              template.innerHTML = html;
              Drupal.detachBehaviors(content, null, 'unload');
              content.replaceChildren(template.content.cloneNode(true));
              Drupal.attachBehaviors(content);
            })
            .catch((error) => {
              if (error.name !== 'AbortError') {
                content.replaceChildren(document.createTextNode(Drupal.t('Não foi possível carregar esta conversa.')));
              }
            })
            .finally(() => {
              if (request === activeRequest) {
                content.setAttribute('aria-busy', 'false');
              }
            });
        };

        drawer.addEventListener('click', (event) => {
          const link = event.target.closest('a[data-aculta-conversation-url], a[data-aculta-drawer-back]');
          if (!link || !drawer.contains(link)) {
            return;
          }
          event.preventDefault();
          navigate(link.dataset.acultaConversationUrl || link.href);
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
              const template = document.createElement('template');
              template.innerHTML = html;
              content.replaceChildren(template.content.cloneNode(true));
              Drupal.attachBehaviors(content);
            })
            .catch((error) => {
              if (error.name !== 'AbortError') {
                content.replaceChildren(document.createTextNode(drawer.dataset.acultaDrawerLabel || Drupal.t('Não foi possível carregar.')));
              }
            })
            .finally(() => {
              if (request === activeRequest) {
                content.setAttribute('aria-busy', 'false');
              }
            });
        });

        drawer.addEventListener('hide.bs.offcanvas', () => request?.abort());
      });
    },
  };

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

    counter.textContent = String(count);
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
